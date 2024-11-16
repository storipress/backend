<?php

namespace App\Listeners\Partners\Shopify;

use App\Events\Entity\Article\AutoPostingPathUpdated;
use App\Events\Partners\Shopify\ArticlesSynced as ShopifyArticleSynced;
use App\Events\Partners\Shopify\RedirectionsSyncing as ShopifyRedirectionsSyncing;
use App\Exceptions\ErrorCode;
use App\Exceptions\ErrorException;
use App\Listeners\Traits\ShopifyTrait;
use App\Models\Tenant;
use App\Models\Tenants\ArticleAutoPosting;
use App\Models\Tenants\Integration;
use App\Queue\Middleware\WithoutOverlapping;
use App\SDK\Shopify\Shopify;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Sentry\State\Scope;
use Throwable;

use function Sentry\captureException;
use function Sentry\withScope;

class HandleRedirections implements ShouldQueue
{
    use InteractsWithQueue;
    use ShopifyTrait;

    /** @var array<int, array<int, string>> */
    public array $blogArticles = [];

    public function __construct(protected readonly Shopify $app) {}

    /**
     * Determine whether the listener should be queued.
     */
    public function shouldQueue(
        ShopifyArticleSynced
        |ShopifyRedirectionsSyncing
        |AutoPostingPathUpdated $event,
    ): bool {
        if ($event instanceof AutoPostingPathUpdated) {
            return $event->articleId === null;
        }

        return true;
    }

    /**
     * Get the middleware the job should pass through.
     *
     * @return array<int, object>
     */
    public function middleware(
        ShopifyArticleSynced
        |ShopifyRedirectionsSyncing
        |AutoPostingPathUpdated $event,
    ): array {
        return [(new WithoutOverlapping($event->tenantId))->dontRelease()];
    }

    public function handle(
        ShopifyArticleSynced
        |ShopifyRedirectionsSyncing
        |AutoPostingPathUpdated $event,
    ): void {
        $tenant = Tenant::find($event->tenantId);

        if (! ($tenant instanceof Tenant)) {
            return;
        }

        $tenant->run(function (Tenant $tenant) use ($event) {
            // get app setup
            $integration = Integration::where('key', 'shopify')->sole();

            $data = $integration->data;

            $configuration = $integration->internals ?: [];

            /** @var string|null $domain */
            $domain = Arr::get($configuration, 'myshopify_domain');

            if (! $domain) {
                throw new ErrorException(ErrorCode::SHOPIFY_INTEGRATION_NOT_CONNECT);
            }

            /** @var string|null $token */
            $token = Arr::get($configuration, 'access_token');

            if (! $token) {
                throw new ErrorException(ErrorCode::SHOPIFY_INTEGRATION_NOT_CONNECT);
            }

            // ensure has write_content scope
            $scopes = Arr::get($configuration, 'scopes');

            if (! is_array($scopes) || ! in_array('write_content', $scopes)) {
                return;
            }

            /** @var string $prefix */
            $prefix = Arr::get($data, 'prefix', Arr::get($configuration, 'prefix', '/a/blog'));

            $postings = ArticleAutoPosting::where('platform', 'shopify')
                ->whereNotNull('target_id')
                ->lazyById();

            $this->app->setShop($domain);

            $this->app->setAccessToken($token);

            $blogs = $this->app->getBlogs();

            $blogsHandle = [];

            foreach ($blogs as $blog) {
                $blogsHandle[$blog['id']] = $blog['handle'];
            }

            $redirects = $this->app->getRedirects();

            $pathRedirects = [];

            foreach ($redirects as $redirect) {
                $pathRedirects[$redirect['path']] = $redirect;
            }

            // create root path
            $this->createRedirect($this->app, $tenant->id, '/blogs', $prefix, $pathRedirects);

            // create desk path
            foreach ($blogs as $blog) {
                $path = sprintf('/blogs/%s', $blog['handle']);

                $appPath = sprintf('%s/desks/%s', $prefix, $blog['handle']);

                try {
                    $this->createRedirect($this->app, $tenant->id, $path, $appPath, $pathRedirects);
                } catch (RequestException $e) {
                    // Error code 422 means the path has already been taken.
                    // If customer's shopify already used this path, we do nothing.
                    if ($e->getCode() !== 422) {
                        captureException($e);
                    }
                }

                sleep(1); // Shopify API rate limit is 2 calls per second
            }

            // create article path
            foreach ($postings as $posting) {
                try {
                    /** @var string $targetId */
                    $targetId = $posting->target_id;

                    if (! Str::contains($targetId, '_')) {
                        Log::channel('slack')->debug(
                            'Shopify target id is not valid, skipping redirect creation',
                            [
                                'tenant' => $event->tenantId,
                                'platform' => 'shopify',
                                'target_id' => $targetId,
                            ],
                        );

                        continue;
                    }

                    [$blogId, $articleId] = explode('_', $targetId);

                    $blogId = (int) $blogId;

                    $articleId = (int) $articleId;

                    if (! isset($this->blogArticles[$blogId])) {
                        $articles = $this->app->getArticles($blogId);

                        $this->blogArticles[$blogId] = [];

                        foreach ($articles as $article) {
                            $this->blogArticles[$blogId][$article['id']] = $article['handle'];
                        }
                    }

                    if (! isset($this->blogArticles[$blogId][$articleId])) {
                        continue;
                    }

                    $path = sprintf('/blogs/%s/%s', $blogsHandle[$blogId], $this->blogArticles[$blogId][$articleId]);

                    $prefix = $posting->prefix ?: '';

                    $pathname = ltrim($posting->pathname ?: '', '/');

                    if (empty($prefix) || empty($pathname)) {
                        continue;
                    }

                    $appPath = sprintf('%s/%s', $prefix, $pathname);

                    $this->createRedirect($this->app, $tenant->id, $path, $appPath, $pathRedirects);

                    sleep(1); // Shopify API rate limit is 2 calls per second
                } catch (Throwable $e) {
                    withScope(function (Scope $scope) use ($event, $posting, $e): void {
                        $scope->setContext('debug', [
                            'tenant' => $event->tenantId,
                            'platform' => 'shopify',
                            'action' => 'create_redirect',
                            'posting_id' => $posting['id'],
                            'target_id' => $posting['target_id'],
                        ]);

                        captureException($e);
                    });
                }
            }
        });
    }
}
