<?php

namespace App\Jobs\RudderStack;

use App\Enums\Credit\State;
use App\Enums\User\Status;
use App\Models\Tenant;
use App\Models\Tenants\Article;
use App\Models\Tenants\Integration;
use App\Models\Tenants\User as TenantUser;
use App\Models\Tenants\UserActivity;
use App\Models\User;
use App\Queue\Middleware\WithoutOverlapping;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Segment\Segment;
use Stevebauman\Location\Facades\Location;
use Stevebauman\Location\Position;
use Stripe\Invoice;
use Throwable;

use function Sentry\captureException;

class SyncUserIdentify extends RudderStack
{
    /**
     * Get the middleware the job should pass through.
     *
     * @return WithoutOverlapping[]
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping($this->id))->dontRelease(),
        ];
    }

    /**
     * Execute the job.
     *
     *
     * @throws BindingResolutionException
     */
    public function handle(): mixed
    {
        if (app()->runningUnitTests()) {
            return null;
        }

        if ($this->id === '1') {
            return null;
        }

        $user = User::with(['tenants', 'tenants.owner'])->find($this->id);

        if (!($user instanceof User)) {
            return null;
        }

        $activeDaysLast7 = 0;

        $activeDaysBoundary = now()->startOfDay()->subDays(7);

        $subscription = $user->subscription();

        if ($subscription !== null && !$subscription->active()) {
            $subscription = null;
        }

        $appsumo = $subscription?->name === 'appsumo';

        $stripeSubscription = null;

        if (!$appsumo) {
            $stripeSubscription = $subscription?->asStripeSubscription();
        }

        Segment::identify([
            'userId' => $this->id,
            'traits' => [
                'environment' => app()->environment(),

                // https://www.notion.so/storipress/9c003850affd4c049c89f6342a2da730?v=c78122b9b3b842098cf4037788e1b83a
                'user_uid' => $this->id,
                'user_email' => $user->email,
                'user_name' => $user->full_name,
                'user_first_name' => $user->first_name,
                'user_last_name' => $user->last_name,
                'user_subscribed' => $user->subscribed(),
                'user_plan_name' => Str::before($subscription?->stripe_price ?: '', '-') ?: null,
                'user_plan_interval' => $appsumo ? 'lifetime' : (Str::afterLast($subscription?->stripe_price ?: '', '-') ?: null),
                'user_plan_seats' => $subscription?->quantity,
                'user_plan_renew_on' => $stripeSubscription ? Carbon::createFromTimestampUTC($stripeSubscription->current_period_end)->toIso8601String() : null,
                'user_revenue' => $revenue = $this->revenue($user),
                'user_has_historical_plan' => $user->subscriptions()->count() > 0,
                'user_facebook' => $this->toSocialUrl($user->socials, 'facebook'),
                'user_instagram' => $this->toSocialUrl($user->socials, 'instagram'),
                'user_twitter' => $this->toSocialUrl($user->socials, 'twitter'),
                'user_signup_source' => $user->signed_up_source,
                'user_business_purpose' => data_get($user->data, 'business_purpose'),
                'user_user_role' => data_get($user->data, 'user_role'),
                'user_publishing_frequency' => data_get($user->data, 'publishing_frequency'),
                'user_refer_source' => data_get($user->data, 'refer_source'),
                'user_credits_earned' => $user->credits()
                    ->whereIn('state', [State::available(), State::used()])
                    ->sum('amount'),
                'user_tenants' => $tenants = $user->tenants
                    ->map(function (Tenant $tenant) use ($user, &$activeDaysLast7, $activeDaysBoundary) {
                        if (!$tenant->initialized) {
                            return null;
                        }

                        $central = $user;

                        return $tenant->run(function (Tenant $tenant) use ($central, &$activeDaysLast7, $activeDaysBoundary) {
                            $users = TenantUser::get(['id', 'role', 'status', 'created_at']);

                            $user = $users->firstWhere('id', $this->id);

                            if (!($user instanceof TenantUser)) {
                                return null;
                            }

                            if (!Status::active()->is($user->status)) {
                                return null;
                            }

                            $subscription = $tenant->owner->subscription();

                            if ($subscription !== null && !$subscription->active()) {
                                $subscription = null;
                            }

                            $integrations = Integration::get();

                            $days = UserActivity::whereUserId($this->id)
                                ->where('occurred_at', '>=', $activeDaysBoundary)
                                ->pluck('occurred_at')
                                ->map(fn (Carbon $occurredAt) => $occurredAt->toDateString())
                                ->unique()
                                ->values()
                                ->count();

                            if ($days > $activeDaysLast7) {
                                $activeDaysLast7 = $days;
                            }

                            $shopify = $integrations->firstWhere('key', 'shopify');

                            return [
                                'tenant_uid' => $tenant->id,
                                'tenant_user_class' => $user->role,
                                'tenant_user_joined_time' => $user->created_at->toIso8601String(),
                                'tenant_user_suspended' => $user->status->isNot(Status::active()) ?: false,
                                'tenant_user_first_feature' => data_get($central->data, sprintf('%s.first_feature', $tenant->id)),
                                'tenant_name' => $tenant->name,
                                'tenant_plan_name' => $plan = Str::before($subscription?->stripe_price ?: '', '-') ?: null,
                                'tenant_plan_seats' => $subscription?->quantity,
                                'tenant_trial_active' => $tenant->owner->onTrial(),
                                'tenant_trial_ends_at' => $tenant->owner->trialEndsAt()?->toIso8601String(),
                                'tenant_team_size' => $teamSize = $users->where('status', Status::active())->count(),
                                'tenant_email' => $tenant->email,
                                'tenant_favicon' => $avatar = is_string($tenant->favicon) ? (Str::startsWith($tenant->favicon, 'data:image/') ? null : $tenant->favicon) : null,
                                'tenant_logo' => $tenant->logo?->url,
                                'tenant_url' => $website = Str::of($tenant->url)->prepend('https://')->value(),
                                'tenant_custom_domain_active' => !empty($tenant->custom_domain),
                                'tenant_customised_theme' => (bool) data_get($tenant, 'tutorials.setCustomiseTheme', false),
                                'tenant_created_at' => $tenant->created_at->toIso8601String(),
                                'tenant_created_by' => (string) $tenant->owner->id,
                                'tenant_last_seen' => UserActivity::latest('occurred_at')->value('occurred_at')?->toIso8601String() ?: $tenant->created_at->toIso8601String(), // @phpstan-ignore-line
                                'tenant_article_created' => Article::where('id', '>', 7)->count(),
                                'tenant_article_scheduled' => Article::where('id', '>', 7)->whereNotNull('published_at')->count(),
                                'tenant_integration_facebook' => $integrations->firstWhere('key', 'facebook')?->activated_at?->toIso8601String(),
                                'tenant_integration_twitter' => $integrations->firstWhere('key', 'twitter')?->activated_at?->toIso8601String(),
                                'tenant_integration_slack' => $integrations->firstWhere('key', 'slack')?->activated_at?->toIso8601String(),
                                'tenant_integration_shopify' => $shopify?->activated_at?->toIso8601String(),
                                'tenant_integration_shopify_store_id' => $shopify?->data['id'] ?? null,
                                'tenant_integration_shopify_domain' => $shopify?->internals['domain'] ?? null,
                                'tenant_integration_shopify_shopify_domain' => $shopify?->internals['myshopify_domain'] ?? null,
                                'tenant_integration_webflow' => $integrations->firstWhere('key', 'webflow')?->activated_at?->toIso8601String(),
                                'tenant_integration_count' => $integrations->whereNotNull('activated_at')->count(),

                                // reserved traits
                                // https://segment.com/docs/connections/spec/group/#traits
                                'id' => $tenant->id,
                                'name' => $tenant->name,
                                'email' => $tenant->email,
                                'description' => $tenant->description,
                                'employees' => (string) $teamSize,
                                'plan' => $plan,
                                'website' => $website,
                                'avatar' => $avatar ?: $tenant->logo?->url,
                                'createdAt' => $tenant->created_at->toIso8601String(),
                            ];
                        });
                    })
                    ->filter()
                    ->take(20)
                    ->values()
                    ->toArray(),
                'user_active_days_past_7' => $activeDaysLast7,
                ...$this->location($user),

                // June.so
                'TCValue' => $revenue,

                // RudderStack reserved traits
                // https://www.rudderstack.com/docs/event-spec/standard-events/identify/#identify-traits
                'email' => $user->email,
                'name' => $user->full_name,
                'firstName' => $user->first_name,
                'lastName' => $user->last_name,
                'description' => $user->bio ?: null,
                'website' => $user->website ?: null,
                'avatar' => str_starts_with($user->avatar, 'https://api.dicebear.com/')
                    ? null
                    : $user->avatar,
                'createdAt' => $user->created_at->toIso8601String(),
            ],
        ]);

        /** @var array<string, mixed> $tenant */
        foreach ($tenants as $tenant) {
            Segment::group([
                'userId' => $this->id,
                'groupId' => $tenant['tenant_uid'],
                'traits' => Arr::except($tenant, [
                    'tenant_user_class',
                    'tenant_user_joined_time',
                    'tenant_user_suspended',
                ]),
            ]);
        }

        return Segment::flush();
    }

    /**
     * @return array{
     *     user_ip: string|null,
     *     user_country: string|null,
     *     user_region: string|null,
     *     user_city: string|null,
     * }
     */
    protected function location(User $user): array
    {
        $ip = $user
            ->accessTokens()
            ->latest('created_at')
            ->value('ip');

        if (!is_string($ip)) {
            $ip = null;
        }

        $location = null;

        if ($ip) {
            $key = sprintf('ip-location:%s', md5($ip));

            try {
                $location = Cache::remember($key, 60 * 60 * 24 * 7, fn () => Location::get($ip));
            } catch (Throwable) {
                //
            }

            if (!($location instanceof Position)) {
                $location = null;

                Cache::forget($key);
            }
        }

        return [
            'user_ip' => $ip,
            'user_country' => $location?->countryCode,
            'user_region' => $location?->regionName,
            'user_city' => $location?->cityName,
        ];
    }

    protected function revenue(User $user): int
    {
        $total = 0;

        if (!$user->hasStripeId()) {
            return $total;
        }

        if ($user->subscriptions()->count() === 0) {
            return $total;
        }

        try {
            $invoices = $user->stripe()->invoices->all([
                'customer' => $user->stripe_id,
                'status' => 'paid',
                'limit' => 100,
            ]);
        } catch (Throwable $e) {
            captureException($e);

            return $total;
        }

        do {
            /** @var Invoice $invoice */
            foreach ($invoices as $invoice) {
                $total += $invoice->total;
            }

            $invoices = $invoices->nextPage();
        } while (!$invoices->isEmpty());

        return $total;
    }

    /**
     * Normalize target social platform url.
     *
     * @param  array<string, string>|null  $socials
     */
    protected function toSocialUrl(?array $socials, string $platform): ?string
    {
        if (empty($socials[$platform])) {
            return null;
        }

        $url = trim($socials[$platform]);

        if (empty($url)) {
            return null;
        }

        $mapping = [
            'facebook' => 'https://www.facebook.com/',
            'twitter' => 'https://twitter.com/',
            'instagram' => 'https://www.instagram.com/',
        ];

        return Str::of($url)
            ->trim()
            ->before('?')
            ->trim('/')
            ->afterLast('/')
            ->prepend($mapping[$platform])
            ->value();
    }
}
