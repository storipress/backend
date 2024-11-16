<?php

namespace App\GraphQL\Mutations\Integration;

use App\Events\Partners\Shopify\OAuthConnected as ShopifyOAuthConnected;
use App\Exceptions\BadRequestHttpException;
use App\Exceptions\ErrorCode;
use App\Exceptions\HttpException;
use App\Exceptions\InvalidCredentialsException;
use App\Models\Tenant;
use App\Models\Tenants\UserActivity;
use App\Resources\Partners\Shopify\Shop;
use App\SDK\Shopify\Shopify;
use Illuminate\Support\Facades\Cache;
use Throwable;

use function Sentry\captureException;

final class SetupShopifyOauth
{
    /**
     * @param  array{
     *     code: string,
     * }  $args
     */
    public function __invoke($_, array $args): bool
    {
        $tenantId = tenant('id');

        if (! is_string($tenantId)) {
            throw new BadRequestHttpException();
        }

        $key = 'shopify-oauth-'.$args['code'];

        $data = tenancy()->central(fn () => Cache::get($key));

        if (! empty($data) && is_array($data)) {
            /** @var array{token: string, scopes: string[], shop: Shop} $data */
            $this->connectShopify($tenantId, $data);

            return true;
        }

        throw new BadRequestHttpException();
    }

    /**
     * @param  array{token: string, scopes: string[], shop: Shop}  $data
     */
    protected function connectShopify(string $tenantId, array $data): void
    {
        $domain = $data['shop']->myshopifyDomain;

        // ensure the token is valid.
        $shopify = new Shopify($domain, $data['token']);

        // ensure the shop has not been connected already.
        $exists = Tenant::where('id', '!=', $tenantId)
            ->whereJsonContains('data->shopify_data->myshopify_domain', $domain)
            ->exists();

        if ($exists) {
            throw new HttpException(ErrorCode::SHOPIFY_SHOP_ALREADY_CONNECTED);
        }

        try {
            $shopify->getWebhooks();
        } catch (Throwable $e) {
            if ($e->getCode() === 401) {
                throw new InvalidCredentialsException();
            }

            captureException($e);

            throw new BadRequestHttpException();
        }

        UserActivity::log(
            name: 'integration.connect',
            data: [
                'key' => 'shopify',
            ],
        );

        ShopifyOAuthConnected::dispatch(
            $data['token'],
            $data['scopes'],
            $data['shop'],
            $tenantId,
        );
    }
}
