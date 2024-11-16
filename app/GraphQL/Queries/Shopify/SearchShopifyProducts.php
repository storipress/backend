<?php

namespace App\GraphQL\Queries\Shopify;

use App\Exceptions\BadRequestHttpException;
use App\Exceptions\ErrorCode;
use App\Exceptions\HttpException;
use App\Models\Tenants\Integration;
use App\SDK\Shopify\Shopify;
use Exception;
use Illuminate\Support\Arr;

use function Sentry\captureException;

final class SearchShopifyProducts
{
    public function __construct(protected readonly Shopify $app) {}

    /**
     * @param  array{keyword: string}  $args
     * @return mixed[]
     */
    public function __invoke($_, array $args): array
    {
        $integration = Integration::activated()->find('shopify');

        if (! ($integration instanceof Integration)) {
            throw new BadRequestHttpException();
        }

        /** @var string|null $domain */
        $domain = Arr::get($integration->data, 'myshopify_domain');

        /** @var string|null $token */
        $token = Arr::get($integration->internals ?: [], 'access_token');

        if (empty($domain) || empty($token)) {
            throw new BadRequestHttpException();
        }

        $scopes = Arr::get($integration->internals ?: [], 'scopes');

        if (! is_array($scopes) || ! in_array('read_products', $scopes, true)) {
            throw new HttpException(ErrorCode::SHOPIFY_MISSING_PRODUCTS_SCOPE);
        }

        $keyword = $args['keyword'];

        try {
            $this->app->setShop($domain);

            $this->app->setAccessToken($token);

            $ids = $this->app->searchProducts($keyword);

            if (empty($ids)) {
                return ['products' => [], 'page_info' => null];
            }

            return $this->app->getProducts(options: ['ids' => $ids]);
        } catch (Exception $e) {
            captureException($e);

            return ['products' => [], 'page_info' => null];
        }
    }
}
