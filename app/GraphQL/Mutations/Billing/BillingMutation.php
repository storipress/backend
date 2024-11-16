<?php

namespace App\GraphQL\Mutations\Billing;

use App\GraphQL\Mutations\Mutation;
use App\GraphQL\Queries\Billing\AppSubscriptionPlans;
use App\Models\Tenant;
use App\Models\Tenants\Integration;
use App\Models\User;
use Stripe\Exception\ApiErrorException;

abstract class BillingMutation extends Mutation
{
    /**
     * @return array<int, string>
     *
     * @throws ApiErrorException
     */
    protected function priceIds(): array
    {
        return array_column(
            (new AppSubscriptionPlans())->prices(),
            'id',
        );
    }

    public function isUsingPlusFeature(User $user): bool
    {
        // @phpstan-ignore-next-line
        return $user->publications->some(function (Tenant $tenant) {
            return $tenant->run(function () {
                return Integration::query()
                    ->withoutEagerLoads()
                    ->whereIn('key', ['webflow'])
                    ->whereNotNull('activated_at')
                    ->exists();
            });
        });
    }
}
