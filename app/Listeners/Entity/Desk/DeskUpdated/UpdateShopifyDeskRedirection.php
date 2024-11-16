<?php

namespace App\Listeners\Entity\Desk\DeskUpdated;

use App\Events\Entity\Desk\DeskUpdated;
use App\Exceptions\ErrorCode;
use App\Exceptions\ErrorException;
use App\Listeners\Traits\ShopifyTrait;
use App\Models\Tenant;
use App\Models\Tenants\Desk;
use App\Models\Tenants\Integration;
use App\Queue\Middleware\WithoutOverlapping;
use App\SDK\Shopify\Shopify;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Arr;
use Throwable;

class UpdateShopifyDeskRedirection implements shouldQueue
{
    use InteractsWithQueue;
    use ShopifyTrait;

    public function __construct(protected readonly Shopify $app) {}

    /**
     * Get the middleware the job should pass through.
     *
     * @return array<int, object>
     */
    public function middleware(DeskUpdated $event): array
    {
        return [(new WithoutOverlapping($event->tenantId))->dontRelease()];
    }

    public function handle(DeskUpdated $event): void
    {
        $tenant = Tenant::find($event->tenantId);

        if (! ($tenant instanceof Tenant)) {
            return;
        }

        $tenant->run(function (Tenant $tenant) use ($event) {
            $desk = Desk::where('id', $event->deskId)
                ->whereNotNull('shopify_id')
                ->first();

            if (! ($desk instanceof Desk)) {
                return;
            }

            // get app setup
            $integration = Integration::where('key', 'shopify')->sole();

            $data = $integration->data;

            $configuration = $integration->internals ?: [];

            /** @var string $prefix */
            $prefix = Arr::get($data, 'prefix', Arr::get($configuration, 'prefix', '/a/blog'));

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

            $this->app->setShop($domain);

            $this->app->setAccessToken($token);

            /** @var int $shopifyId */
            $shopifyId = $desk->shopify_id;

            try {
                $blog = $this->app->getBlog($shopifyId);

                $redirects = $this->app->getRedirects();

                /** @var string $handle */
                $handle = $blog['handle'];

                $path = sprintf('/blogs/%s', $handle);

                /** @var string $slug */
                $slug = $desk->slug;

                $appPath = sprintf('%s/desks/%s', $prefix, $slug);

                $pathRedirects = [];

                foreach ($redirects as $redirect) {
                    $pathRedirects[$redirect['path']] = $redirect;
                }

                $this->createRedirect($this->app, $tenant->id, $path, $appPath, $pathRedirects);
            } catch (Throwable $e) {
                if ($e->getCode() === 404) {
                    $desk->shopify_id = null;

                    $desk->save();

                    return;
                }
            }
        });
    }
}
