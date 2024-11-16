<?php

namespace App\Http\Controllers;

use App\Events\Entity\Subscription\SubscriptionPlanChanged;
use App\Mail\UserAppSumoRefundMail;
use App\Models\User;
use App\Models\UserActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Laravel\Cashier\Subscription;
use Laravel\Cashier\SubscriptionItem;
use Lcobucci\JWT\Encoding\CannotDecodeContent;
use Lcobucci\JWT\Token\InvalidTokenStructure;
use Lcobucci\JWT\Token\UnsupportedHeaderFound;
use Segment\Segment;
use Webmozart\Assert\Assert;

class AppSumoNotificationController extends Controller
{
    /**
     * Handle the incoming request.
     *
     *
     * @see https://appsumo.com/partners/licensing-guide/
     */
    public function __invoke(Request $request): JsonResponse
    {
        $jwt = $request->bearerToken() ?: '';

        if (empty($jwt)) {
            return $this->error('invalid token');
        }

        try {
            $token = app('jwt.parser')->parse($jwt);
        } catch (InvalidTokenStructure|CannotDecodeContent|UnsupportedHeaderFound) {
            return $this->error('invalid token');
        }

        if ($token->isExpired(now())) {
            return $this->error('invalid token');
        }

        $actions = [
            'activate', // Sumo-ling has since purchased a product license and is now looking to activate/redeem
            'enhance_tier', // Sumo-ling upgrades their license to a larger plan
            'reduce_tier', // Sumo-ling downgrades their license to a smaller plan
            'refund', // Sumo-ling returns their license for a refund
            'update', // Sumo-ling has successfully completed an enhance (upgrade) or reduce (downgrade) tier action
        ];

        $action = $request->input('action');

        if (!in_array($action, $actions, true)) {
            return $this->error('invalid payload');
        }

        $handler = sprintf('handle%s', Str::studly($action));

        if (!method_exists($this, $handler)) {
            return $this->error('internal error');
        }

        return $this->{$handler}($request);
    }

    /**
     * @see https://appsumo.com/partners/licensing-guide/#activation
     */
    protected function handleActivate(Request $request): JsonResponse
    {
        $email = $request->input('activation_email');

        if (!is_not_empty_string($email)) {
            return $this->error('Something went wrong in the internal service.');
        }

        $email = Str::lower($email);

        $token = Str::random(10);

        $user = User::whereEmail($email)->first();

        if ($user === null) {
            $user = User::create([
                'email' => $email,
                'password' => Hash::make($token),
                'first_name' => 'AppSumo',
                'last_name' => $token,
                'signed_up_source' => 'appsumo',
            ]);
        } elseif ($user->subscribed()) {
            return $this->error('You already had an active subscription or license.');
        } elseif ($user->subscriptions()->where('name', '=', 'default')->exists()) {
            return $this->error('The AppSumo deal is not available to existing customers.');
        }

        $plan = $request->input('plan_id');

        $quantity = $this->quantity($plan);

        $subscription = $user->subscriptions()->create([
            'name' => 'appsumo',
            'stripe_id' => $request->input('uuid'),
            'stripe_status' => 'active',
            'stripe_price' => $plan,
            'quantity' => $quantity,
        ]);

        Assert::isInstanceOf($subscription, Subscription::class);

        $subscription->items()->create([
            'stripe_id' => $request->input('invoice_item_uuid'),
            'stripe_product' => $request->input('uuid'),
            'stripe_price' => $plan,
            'quantity' => $quantity,
        ]);

        $this->updatePublicationPlan($subscription);

        UserActivity::log(
            name: 'billing.subscription.create',
            subject: $subscription,
            userId: $user->id,
        );

        Segment::track([
            'userId' => (string) $user->id,
            'event' => 'user_subscription_created',
            'properties' => [
                'type' => 'appsumo',
                'subscription_id' => $subscription->id,
                'partner_id' => $request->input('uuid'),
                'plan_id' => $plan,
            ],
        ]);

        Cache::put('appsumo-' . $token, $email);

        $base = config('app.url');

        if (!is_string($base)) {
            $base = 'https://stori.press';
        }

        $query = http_build_query([
            'source' => 'appsumo',
            'email' => $email,
            'appsumo_code' => $token,
        ]);

        $url = Str::of($base)
            ->replace('api.', '')
            ->rtrim('/')
            ->append('/auth/')
            ->append($user->wasRecentlyCreated ? 'signup' : 'login')
            ->append('?')
            ->append($query)
            ->toString();

        return $this->ok(
            'You had activated your product successfully.',
            201,
            ['redirect_url' => $url],
        );
    }

    /**
     * @see https://appsumo.com/partners/licensing-guide/#enhance_section
     */
    protected function handleEnhanceTier(Request $request): JsonResponse
    {
        $subscription = $this->subscription($request->input('uuid'));

        $subscription->update([
            'stripe_price' => $request->input('plan_id'),
            'quantity' => $this->quantity($request->input('plan_id')),
        ]);

        UserActivity::log(
            name: 'billing.subscription.upgrade',
            subject: $subscription,
            userId: $subscription->user_id,
        );

        Segment::track([
            'userId' => (string) $subscription->user_id,
            'event' => 'user_subscription_upgraded',
            'properties' => [
                'type' => 'appsumo',
                'subscription_id' => $subscription->id,
                'partner_id' => $request->input('uuid'),
                'plan_id' => $request->input('plan_id'),
            ],
        ]);

        return $this->ok('You had enhanced your tier successfully.');
    }

    /**
     * @see https://appsumo.com/partners/licensing-guide/#reduce_section
     */
    protected function handleReduceTier(Request $request): JsonResponse
    {
        $subscription = $this->subscription($request->input('uuid'));

        $subscription->update([
            'stripe_price' => $request->input('plan_id'),
            'quantity' => $this->quantity($request->input('plan_id')),
        ]);

        UserActivity::log(
            name: 'billing.subscription.downgrade',
            subject: $subscription,
            userId: $subscription->user_id,
        );

        Segment::track([
            'userId' => (string) $subscription->user_id,
            'event' => 'user_subscription_downgraded',
            'properties' => [
                'type' => 'appsumo',
                'subscription_id' => $subscription->id,
                'partner_id' => $request->input('uuid'),
                'plan_id' => $request->input('plan_id'),
            ],
        ]);

        return $this->ok('You had reduced your tier successfully.');
    }

    /**
     * @see https://appsumo.com/partners/licensing-guide/#refund_section
     */
    protected function handleRefund(Request $request): JsonResponse
    {
        $subscription = $this->subscription($request->input('uuid'));

        $subscription->update([
            'stripe_status' => 'canceled',
            'ends_at' => now(),
        ]);

        $this->updatePublicationPlan($subscription);

        $user = $subscription->owner()->first();

        if ($user instanceof User) {
            Mail::to($user->email)->send(new UserAppSumoRefundMail());
        }

        UserActivity::log(
            name: 'billing.subscription.cancel',
            subject: $subscription,
            userId: $subscription->user_id,
        );

        Segment::track([
            'userId' => (string) $subscription->user_id,
            'event' => 'user_subscription_canceled',
            'properties' => [
                'type' => 'appsumo',
                'subscription_id' => $subscription->id,
                'partner_id' => $request->input('uuid'),
                'plan_id' => $subscription->stripe_price,
            ],
        ]);

        return $this->ok('You had refunded your product successfully.');
    }

    /**
     * @see https://appsumo.com/partners/licensing-guide/#update_section
     */
    protected function handleUpdate(Request $request): JsonResponse
    {
        $subscription = $this->subscription($request->input('uuid'));

        $item = $subscription->items()->first();

        Assert::isInstanceOf($item, SubscriptionItem::class);

        $item->update([
            'stripe_id' => $request->input('invoice_item_uuid'),
            'stripe_price' => $request->input('plan_id'),
            'quantity' => $this->quantity($request->input('plan_id')),
        ]);

        $this->updatePublicationPlan($subscription);

        return $this->ok('Information updated.');
    }

    protected function updatePublicationPlan(Subscription $subscription): bool
    {
        $user = $subscription->owner()->sole();

        Assert::isInstanceOf($user, User::class);

        SubscriptionPlanChanged::dispatch(
            $user->id,
            $subscription->ended()
                ? 'free'
                : ($subscription->stripe_price ?: 'free'),
        );

        return true;
    }

    /**
     * Get quantity for different plans.
     */
    protected function quantity(mixed $plan): ?int
    {
        return match ($plan) {
            'storipress_tier1' => 1,
            'storipress_tier2' => 3,
            'storipress_tier3' => 8,
            'storipress_bf_tier1' => 1,
            'storipress_bf_tier2' => 3,
            'storipress_bf_tier3' => 8,
            default => null,
        };
    }

    protected function subscription(mixed $uuid): Subscription
    {
        if (!is_string($uuid) || !Str::isUuid($uuid)) {
            throw new InvalidArgumentException('Invalid uuid value: ' . $uuid);
        }

        return Subscription::whereStripeId($uuid)->sole();
    }

    /**
     * Response wrapper.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function ok(string $message, int $code = 200, array $payload = []): JsonResponse
    {
        return response()->json(array_merge($payload, ['message' => $message]), $code);
    }

    /**
     * Response wrapper.
     */
    protected function error(string $message): JsonResponse
    {
        return response()->json(['message' => $message], 403);
    }
}
