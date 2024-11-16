<?php

namespace App\Listeners\Entity\Article\AutoPostingPathUpdated;

use App\Events\Entity\Article\AutoPostingPathUpdated;
use App\Exceptions\ErrorCode;
use App\Exceptions\ErrorException;
use App\Listeners\Traits\ShopifyTrait;
use App\Models\Tenant;
use App\Models\Tenants\Article;
use App\Models\Tenants\Integration;
use App\Queue\Middleware\WithoutOverlapping;
use App\SDK\Shopify\Shopify;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Arr;
use Sentry\State\Scope;
use Throwable;

use function Sentry\captureException;
use function Sentry\withScope;

class HandleShopifyArticleRedirection implements ShouldQueue
{
    use InteractsWithQueue;
    use ShopifyTrait;

    public function __construct(protected readonly Shopify $app) {}

    /**
     * Determine whether the listener should be queued.
     */
    public function shouldQueue(AutoPostingPathUpdated $event): bool
    {
        return $event->platform === 'shopify' && $event->articleId !== null;
    }

    /**
     * Get the middleware the job should pass through.
     *
     * @return array<int, object>
     */
    public function middleware(AutoPostingPathUpdated $event): array
    {
        return [(new WithoutOverlapping($event->tenantId))->dontRelease()];
    }

    public function handle(AutoPostingPathUpdated $event): void
    {
        $tenant = Tenant::find($event->tenantId);

        if (! ($tenant instanceof Tenant)) {
            return;
        }

        $tenant->run(function (Tenant $tenant) use ($event) {
            // get app setup
            $integration = Integration::where('key', 'shopify')->sole();

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

            $posting = Article::find($event->articleId)
                ?->autoPostings()
                ->where('platform', 'shopify')
                ->whereNotNull('target_id')
                ->first();

            if (! $posting) {
                return;
            }

            $this->app->setShop($domain);

            $this->app->setAccessToken($token);

            $redirects = $this->app->getRedirects();

            $pathRedirects = [];

            foreach ($redirects as $redirect) {
                $pathRedirects[$redirect['path']] = $redirect;
            }

            try {
                /** @var string $targetId */
                $targetId = $posting->target_id;

                [$blogId, $articleId] = explode('_', $targetId);

                $blogId = (int) $blogId;

                $articleId = (int) $articleId;

                $blog = $this->app->getBlog($blogId);

                $article = $this->app->getArticle($blogId, $articleId);

                $path = sprintf('/blogs/%s/%s', $blog['handle'], $article['handle']);

                $prefix = $posting->prefix ?: '';

                $pathname = ltrim($posting->pathname ?: '', '/');

                if (empty($prefix) || empty($pathname)) {
                    return;
                }

                $appPath = sprintf('%s/%s', $prefix, $pathname);

                $this->createRedirect($this->app, $tenant->id, $path, $appPath, $pathRedirects);
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
        });
    }
}
