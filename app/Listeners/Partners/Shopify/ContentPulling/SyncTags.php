<?php

namespace App\Listeners\Partners\Shopify\ContentPulling;

use App\Events\Partners\Shopify\ContentPulling;
use App\Exceptions\ErrorCode;
use App\Exceptions\ErrorException;
use App\Models\Tenant;
use App\Models\Tenants\Integration;
use App\Models\Tenants\Tag;
use App\Queue\Middleware\WithoutOverlapping;
use App\SDK\Shopify\Shopify;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Arr;
use Sentry\State\Scope;
use Throwable;

use function Sentry\captureException;
use function Sentry\withScope;

class SyncTags implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(protected readonly Shopify $app)
    {
    }

    /**
     * Get the middleware the job should pass through.
     *
     * @return array<int, object>
     */
    public function middleware(ContentPulling $event): array
    {
        return [(new WithoutOverlapping($event->tenantId))->dontRelease()];
    }

    public function handle(ContentPulling $event): void
    {
        $tenant = Tenant::where('id', $event->tenantId)->sole();

        $result = $tenant->run(function () {
            $count = 0;

            try {
                $integration = Integration::where('key', 'shopify')->sole();

                $internals = $integration->internals ?: [];

                /** @var string|null $domain */
                $domain = Arr::get($internals, 'myshopify_domain');

                if (!$domain) {
                    throw new ErrorException(ErrorCode::SHOPIFY_INTEGRATION_NOT_CONNECT);
                }

                /** @var string|null $token */
                $token = Arr::get($internals, 'access_token');

                if (!$token) {
                    throw new ErrorException(ErrorCode::SHOPIFY_INTEGRATION_NOT_CONNECT);
                }

                $this->app->setAccessToken($token);

                $this->app->setShop($domain);

                $tags = $this->app->getTags();

                foreach ($tags as $tag) {
                    Tag::firstOrCreate(['name' => $tag]);

                    ++$count;
                }

                return $count;
            } catch (Throwable $e) {
                return $e;
            }
        });

        if ($result instanceof Throwable) {
            withScope(function (Scope $scope) use ($result, $event): void {
                $scope->setContext('debug', [
                    'tenant' => $event->tenantId,
                    'platform' => 'shopify',
                    'action' => 'pull_tags',
                ]);

                captureException($result);
            });
        }
    }
}
