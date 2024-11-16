<?php

namespace App\SDK\Shopify;

use App\SDK\SocialPlatformsInterface;
use Exception;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use SocialiteProviders\Manager\OAuth2\User;
use SocialiteProviders\Shopify\Provider as ShopifyProvider;
use Webmozart\Assert\Assert;

class Shopify implements SocialPlatformsInterface
{
    protected ShopifyProvider $client;

    protected const API_VERSION = '2023-07';

    /**
     * @var string[]
     */
    protected array $scopes = [
        'read_customers',
        'read_content',
        'write_content', // for blog redirect
        'write_themes', // for meta header
    ];

    public function __construct(protected ?string $shop = null, protected ?string $token = null)
    {
        $client = Socialite::driver('shopify');

        Assert::isInstanceOf($client, ShopifyProvider::class);

        $this->client = $client;

        $this->client->stateless();
    }

    public function setShop(string $shop): void
    {
        $this->shop = $shop;
    }

    public function setAccessToken(string $token): void
    {
        $this->token = $token;
    }

    public function http(): PendingRequest
    {
        $base = sprintf(
            'https://%s/admin/api/%s',
            $this->shop,
            self::API_VERSION,
        );

        return app('http2')
            ->baseUrl($base)
            ->withHeaders([
                'X-Shopify-Access-Token' => $this->token,
            ]);
    }

    public function redirect(string $token, string $domain = ''): RedirectResponse
    {
        if (request()->has('shop')) {
            return $this->client
                ->with(['state' => $token])
                ->scopes($this->scopes)
                ->redirectUrl(Str::finish(route('oauth.shopify'), '/'))
                ->redirect();
        }

        $params = [
            'client_id' => config('services.shopify.client_id'),
            'redirect_uri' => Str::finish(route('oauth.shopify'), '/'),
            'scope' => implode(',', $this->scopes),
            'state' => $token,
        ];

        $url = sprintf('https://%s/admin/oauth/authorize?%s', $domain, http_build_query($params));

        return Redirect::away($url);
    }

    public function revoke(string $domain, string $token): bool
    {
        $url = sprintf(
            'https://%s/admin/api_permissions/current.json',
            $domain,
        );

        $response = app('http')
            ->withHeaders([
                'X-Shopify-Access-Token' => $token,
            ])
            ->delete($url);

        return $response->ok();
    }

    public function user(): User
    {
        $user = $this->client->user();

        Assert::isInstanceOf($user, User::class);

        return $user;
    }

    /**
     * @param  string[]  $params
     * @return array<array<mixed>>
     */
    public function list(string $type, array $params = [], ?string $route = null): array
    {
        $results = [];

        $pageInfo = '';

        $fields = implode(',', $params);

        $path = $route ?: sprintf('/%s.json', $type);

        while (1) {
            $response = retry(
                3,
                fn () => $this->http()->get(
                    $path,
                    [
                        'limit' => 250,
                        'fields' => $fields,
                        'page_info' => $pageInfo,
                    ],
                )->throw(),
                1000,
                fn (Exception $e) => $e->getCode() === 429,
            );

            /** @var array<array<mixed>> $data */
            $data = $response->json($type);

            foreach ($data as $value) {
                $only = Arr::only($value, $params);

                $results[] = $only;
            }

            $link = $response->header('link');

            // the total count less than limit.
            if (empty($link)) {
                break;
            }

            $pageInfo = $this->getNextPageInfo($link);

            // last page.
            if ($pageInfo === null) {
                break;
            }
        }

        return $results;
    }

    /**
     * @return array<array{id: string, email: string|null, accepts_marketing: bool, first_name: string, last_name: string}>
     */
    public function getCustomers(): array
    {
        /** @var array<array{id: string, email: string|null, accepts_marketing: bool, first_name: string, last_name: string}> $result */
        $result = $this->list('customers', [
            'id',
            'email',
            'first_name',
            'last_name',
            'accepts_marketing',
        ]);

        return $result;
    }

    /**
     * @return string[]
     *
     * @throws Exception
     */
    public function getTags(): array
    {
        $response = retry(
            3,
            fn () => $this->http()
                ->get('/articles/tags.json')
                ->throw(),
            1000,
            fn (Exception $e) => $e->getCode() === 429,
        );

        $tags = $response->json('tags');

        Assert::isArray($tags);

        return $tags;
    }

    /**
     * @return array<array{id: int, title: string, handle: string}>
     */
    public function getBlogs(): array
    {
        /** @var array<array{id: int, title: string, handle: string}> $result */
        $result = $this->list('blogs', ['id', 'handle', 'title']);

        return $result;
    }

    /**
     * @return array{id: int, handle: string}
     */
    public function getBlog(int $blogId): array
    {
        /** @var array{id: int, handle: string} $result */
        $result = $this->http()->get(
            sprintf('/blogs/%s.json', $blogId),
            [
                'fields' => implode(',', ['id', 'handle']),
            ],
        )->throw()->json('blog');

        return $result;
    }

    /**
     * @return array<array{id: int, handle: string, title: string, body_html: string, summary_html: string, tags: string, published_at: string|null, image?: array{url: string}}>
     */
    public function getArticles(int $blogId): array
    {
        /** @var array<array{id: int, handle: string, title: string, body_html: string, summary_html: string, tags: string, published_at: string|null, image?: array{url: string}}> $result */
        $result = $this->list(
            'articles',
            ['id', 'handle', 'title', 'body_html', 'summary_html', 'tags', 'published_at', 'image'],
            sprintf('/blogs/%s/articles.json', $blogId),
        );

        return $result;
    }

    /**
     * @return array{id: int, handle: string}
     */
    public function getArticle(int $blogId, int $articleId): array
    {
        /** @var array{id: int, handle: string} $result */
        $result = $this->http()->get(
            sprintf('/blogs/%s/articles/%s.json', $blogId, $articleId),
            [
                'fields' => implode(',', ['id', 'handle']),
            ],
        )->throw()->json('article');

        return $result;
    }

    /**
     * @param  array{ids?: array<int, int>}|null  $options
     * @return array{
     *     products: array<array{
     *       id: int,
     *       title: string,
     *       slug: string,
     *       path: string,
     *       images: array<array{id: int, src: string, width: int, height: int}>,
     *       variants: array<array{id: int, title: string, price: string, sku: string, images: array<array{id: int, src: string, width: int, height: int}>}>
     *     }>,
     *     page_info: string|null
     *  }
     *
     * @throws Exception
     */
    public function getProducts(?string $pageInfo = null, ?array $options = null): array
    {
        $results = [
            'products' => [],
            'page_info' => null,
        ];

        $params = [
            'limit' => 250,
            'fields' => 'id,title,tags,handle,variants,images',
        ];

        // status cannot be passed when page_info is present.
        if (empty($pageInfo)) {
            $params['status'] = 'active';
        } else {
            $params['page_info'] = $pageInfo;
        }

        if (!empty($options['ids'])) {
            $params['ids'] = implode(',', $options['ids']);
        }

        $response = retry(
            3,
            fn () => $this->http()
                ->get('/products.json', $params)
                ->throw(),
            1000,
            fn (Exception $e) => $e->getCode() === 429,
        );

        /** @var array<array{id: int, title: string, handle: string, variants: array<array{id: int, title: string, sku: string, price: string}>, images: array<array{id: int, src: string, width: int, height: int, variant_ids: int[]}>}> $products */
        $products = $response->json('products');

        foreach ($products as $product) {
            $data = [
                'id' => $product['id'],
                'title' => $product['title'],
                'slug' => $product['handle'],
                'path' => sprintf('/products/%s', $product['handle']),
                'variants' => [],
                'images' => [],
            ];

            $images = $product['images'];
            $imagesMapping = [];

            foreach ($images as $image) {
                $value = [
                    'id' => $image['id'],
                    'src' => $image['src'],
                    'width' => $image['width'],
                    'height' => $image['height'],
                ];

                if (empty($image['variant_ids'])) {
                    $data['images'][] = $value;

                    continue;
                }

                foreach ($image['variant_ids'] as $variantId) {
                    $imagesMapping[$variantId][] = $value;
                }
            }

            $variants = $product['variants'];

            foreach ($variants as $variant) {
                $data['variants'][] = [
                    'id' => $variant['id'],
                    'title' => $variant['title'],
                    'sku' => $variant['sku'],
                    'price' => $variant['price'],
                    'images' => $imagesMapping[$variant['id']] ?? [],
                ];
            }

            $results['products'][] = $data;
        }

        $link = $response->header('link');

        // the total count less than limit.
        if (!empty($link)) {
            $pageInfo = $this->getNextPageInfo($link);

            $results['page_info'] = $pageInfo;
        }

        return $results;
    }

    /**
     * @return int[]
     *
     * @see https://shopify.dev/docs/api/admin-graphql/2023-01/queries/products#argument-products-query
     */
    public function searchProducts(string $keyword): array
    {
        $response = $this->http()->post('/graphql.json', [
            'query' => <<<QUERY
                {
                    products(query: "{$keyword}", first: 25) {
                        nodes {
                          id
                        }
                    }
                }
                QUERY,
        ]);

        /** @var string[] $ids */
        $ids = $response->json('data.products.nodes.*.id');

        return array_map(fn (string $id) => intval(Str::afterLast($id, '/')), $ids);
    }

    /**
     * @param  string  $link  link header
     *
     * @link https://shopify.dev/docs/api/usage/pagination-rest
     */
    protected function getNextPageInfo(string $link): ?string
    {
        $parts = explode(', ', $link);

        foreach ($parts as $part) {
            if (!Str::contains($part, 'rel="next"')) {
                continue;
            }

            $url = Str::between($part, '<', '>');

            /** @var string $query */
            $query = parse_url($url, PHP_URL_QUERY);

            parse_str($query, $params);

            /** @var array{page_info: string} $params */

            return $params['page_info'];
        }

        return null;
    }

    /**
     * @return string[]
     */
    public function getWebhooks(): array
    {
        $response = $this->http()->get('/webhooks.json');

        /** @var array{array{id: int, topic: string}} $webhooks */
        $webhooks = $response->json('webhooks');

        $results = [];

        foreach ($webhooks as $webhook) {
            $results[] = $webhook['topic'];
        }

        return $results;
    }

    /**
     * @param  string[]  $fields
     * @return array{code: int, errors: array<mixed>|string|null}
     */
    public function registerWebhook(string $topic, array $fields = []): array
    {
        $response = $this->http()->post(
            '/webhooks.json',
            [
                'webhook' => [
                    'topic' => $topic,
                    'address' => route('shopify.events'),
                    'fields' => $fields,
                ],
            ]);

        /** @var array<mixed>|string|null $errors */
        $errors = $response->json('errors');

        return [
            'code' => $response->status(),
            'errors' => $errors,
        ];
    }

    /**
     * @return array{redirects: array{id: int, path: string, target: string}}
     */
    public function getRedirects(): array
    {
        /** @var array{redirects: array{id: int, path: string, target: string}} $result */
        $result = $this->list('redirects', ['id', 'path', 'target']);

        return $result;
    }

    /**
     * @return array{redirect: array{id: int, path: string, target: string}}
     */
    public function createRedirect(string $path, string $target): array
    {
        /** @var array{redirect: array{id: int, path: string, target: string}} $data */
        $data = $this->http()->post(
            '/redirects.json',
            [
                'redirect' => [
                    'path' => $path,
                    'target' => $target,
                ],
            ],
        )->throw()->json();

        return $data;
    }

    public function updateRedirect(int $id, string $path, string $target): bool
    {
        $response = $this->http()->put(
            sprintf('/redirects/%s.json', $id),
            [
                'redirect' => [
                    'path' => $path,
                    'target' => $target,
                ],
            ],
        );

        return $response->ok();
    }

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     role: string,
     *     created_at: string,
     *     updated_at: string
     * }| null
     */
    public function getMainTheme(): ?array
    {
        $response = $this->http()->get('/themes.json');

        /** @var array{array{id: int, name: string, role: string, created_at: string, updated_at: string}} $themes */
        $themes = $response->json('themes');

        foreach ($themes as $theme) {
            if ($theme['role'] !== 'main') {
                continue;
            }

            return $theme;
        }

        return null;
    }

    /**
     * Get the theme liquid asset.
     *
     * @return array{
     *     key: string,
     *     value: string
     * }|null
     */
    public function getThemeLiquidAsset(int $themeId): ?array
    {
        $url = sprintf('/themes/%s/assets.json', $themeId);

        $response = $this->http()->get($url, [
            'asset[key]' => 'layout/theme.liquid',
        ]);

        /** @var array{key: string, value: string}|null $asset */
        $asset = $response->json('asset');

        return $asset;
    }

    /**
     * Update the theme liquid asset.
     */
    public function updateThemeLiquidAsset(int $themeId, string $value): bool
    {
        $url = sprintf('/themes/%s/assets.json', $themeId);

        $response = $this->http()->put($url, [
            'asset' => [
                'key' => 'layout/theme.liquid',
                'value' => $value,
            ],
        ]);

        return $response->ok();
    }
}
