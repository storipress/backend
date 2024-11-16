<?php

namespace App\GraphQL\Mutations\Sync;

use App\Exceptions\BadRequestHttpException;
use App\GraphQL\Mutations\Mutation;
use App\Jobs\Shopify\PullCustomers;
use App\Models\Tenant;
use App\Models\Tenants\Integration;
use App\Models\Tenants\UserActivity;
use Webmozart\Assert\Assert;

class PullShopifyCustomers extends Mutation
{
    /**
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args): bool
    {
        $tenant = tenant();

        Assert::isInstanceOf($tenant, Tenant::class);

        /** @var string $tenantId */
        $tenantId = $tenant->getKey();

        $shopify = Integration::where('key', 'shopify')
            ->activated()
            ->first();

        if ($shopify === null) {
            throw new BadRequestHttpException();
        }

        if (! data_get($shopify, 'data.sync_customers')) {
            throw new BadRequestHttpException();
        }

        PullCustomers::dispatch($tenantId);

        UserActivity::log(
            name: 'sync.shopify.customers',
        );

        return true;
    }
}
