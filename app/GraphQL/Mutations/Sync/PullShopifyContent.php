<?php

namespace App\GraphQL\Mutations\Sync;

use App\Events\Partners\Shopify\ContentPulling as ShopifyContentPulling;
use App\Exceptions\ErrorCode;
use App\Exceptions\HttpException;
use App\GraphQL\Mutations\Mutation;
use App\Models\Tenant;
use App\Models\Tenants\Integration;
use App\Models\Tenants\UserActivity;
use Illuminate\Support\Arr;
use Webmozart\Assert\Assert;

class PullShopifyContent extends Mutation
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
            throw new HttpException(ErrorCode::SHOPIFY_NOT_ACTIVATED);
        }

        $internals = $shopify->internals;

        if (empty($internals)) {
            throw new HttpException(ErrorCode::SHOPIFY_INTEGRATION_NOT_CONNECT);
        }

        // ensure has read_content scope
        $scopes = Arr::get($internals, 'scopes', []);

        if (!is_array($scopes)) {
            // something wrong when saving integration
            throw new HttpException(ErrorCode::SHOPIFY_INTERNAL_ERROR);
        }

        /**
         * The version before SPMVP-6107 only has read_content scope,
         * so we need to check if read_content scope is included
         *
         * write_content contains read_content permission
         */
        if (!in_array('read_content', $scopes) && !in_array('write_content', $scopes)) {
            throw new HttpException(ErrorCode::SHOPIFY_MISSING_REQUIRED_SCOPE, ['scope' => 'read_content']);
        }

        ShopifyContentPulling::dispatch($tenantId);

        UserActivity::log(
            name: 'sync.shopify.content',
        );

        return true;
    }
}
