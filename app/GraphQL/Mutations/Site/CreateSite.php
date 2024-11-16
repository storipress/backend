<?php

namespace App\GraphQL\Mutations\Site;

use App\Exceptions\QuotaExceededHttpException;
use App\GraphQL\Mutations\Mutation;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserActivity;
use Illuminate\Support\Str;
use Webmozart\Assert\Assert;

class CreateSite extends Mutation
{
    /**
     * @param array{
     *     name: string,
     *     invites: array<int, string>,
     *     timezone?: string,
     * } $args
     */
    public function __invoke($_, array $args): string
    {
        /** @var User $user */
        $user = auth()->user();

        Assert::isInstanceOf($user, User::class);

        $user->loadCount('publications');

        if ($user->publications_count >= $this->quota($user)) {
            throw new QuotaExceededHttpException();
        }

        $workspace = sprintf(
            '%s-%s',
            Str::limit(Str::slug($args['name']), 27, ''),
            Str::lower(Str::random(4)),
        );

        $invites = array_values(array_filter(
            array_unique($args['invites']),
            fn (string $email): bool => $email !== $user->email,
        ));

        /** @var Tenant $tenant */
        $tenant = $user->tenants()->create([
            'user_id' => $user->getKey(),
            'name' => $args['name'],
            'workspace' => trim($workspace, '-'),
            'timezone' => $args['timezone'] ?? 'UTC',
            'invites' => $invites,
        ], [
            'role' => 'owner',
        ]);

        UserActivity::log(
            name: 'publication.create',
            subject: $tenant,
        );

        return $tenant->id;
    }

    /**
     * Get user publications quota for current subscription.
     */
    protected function quota(User $user): int
    {
        if ($user->onGenericTrial()) {
            return $this->getQuota('enterprise');
        }

        $subscribed = $user->subscribed();

        if (! $subscribed) {
            return $this->getQuota('free');
        }

        $subscription = $user->subscription();

        if ($subscription === null) {
            return $this->getQuota('free');
        }

        if ($subscription->name === 'appsumo') {
            if ($subscription->stripe_price === 'storipress_tier3' || $subscription->stripe_price === 'storipress_bf_tier3') {
                return $this->getQuota('enterprise');
            }

            return $subscription->quantity ?: 1;
        }

        $plan = Str::before($subscription->stripe_price ?: '', '-');

        return $this->getQuota($plan);
    }

    protected function getQuota(string $plan): int
    {
        $key = sprintf('billing.quota.publications.%s', $plan);

        $quota = config($key);

        if (! is_int($quota)) {
            return 1;
        }

        return $quota;
    }
}
