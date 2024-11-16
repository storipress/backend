<?php

namespace App\GraphQL\Mutations\Integration;

use App\Events\Partners\Shopify\ThemeTemplateInjecting as ShopifyThemeHeadTopSyncing;
use App\Exceptions\ErrorCode;
use App\Exceptions\HttpException;
use App\Models\Tenant;
use App\Models\Tenants\Integration;
use Illuminate\Support\Arr;
use Webmozart\Assert\Assert;

final class InjectShopifyThemeTemplate
{
    /**
     * @param  array{
     *     code: string,
     * }  $args
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

        // ensure has write_themes scope
        $scopes = Arr::get($internals, 'scopes', []);

        if (!is_array($scopes)) {
            // something wrong when saving integration
            throw new HttpException(ErrorCode::SHOPIFY_INTERNAL_ERROR);
        }

        if (!in_array('write_themes', $scopes)) {
            throw new HttpException(ErrorCode::SHOPIFY_MISSING_REQUIRED_SCOPE, ['scope' => 'write_themes']);
        }

        // runs the setup event
        ShopifyThemeHeadTopSyncing::dispatch($tenantId);

        return true;
    }
}
