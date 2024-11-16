<?php

namespace App\Listeners\Partners\Shopify;

use App\Events\Partners\Shopify\ThemeTemplateInjecting;
use App\Events\Partners\Shopify\WebhookReceived;
use App\Exceptions\ErrorCode;
use App\Exceptions\ErrorException;
use App\Listeners\Traits\ShopifyTrait;
use App\Models\Tenant;
use App\Models\Tenants\Integration;
use App\Queue\Middleware\WithoutOverlapping;
use App\SDK\Shopify\Shopify;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Arr;

class HandleThemeTemplateInjection implements ShouldQueue
{
    use InteractsWithQueue;
    use ShopifyTrait;

    public function __construct(protected readonly Shopify $app)
    {
    }

    public function shouldQueue(ThemeTemplateInjecting|WebhookReceived $event): bool
    {
        if ($event instanceof WebhookReceived) {
            return $event->topic === 'themes/publish';
        }

        return true;
    }

    /**
     * Get the middleware the job should pass through.
     *
     * @return array<int, object>
     */
    public function middleware(ThemeTemplateInjecting|WebhookReceived $event): array
    {
        if ($event instanceof ThemeTemplateInjecting) {
            return [(new WithoutOverlapping($event->tenantId))->dontRelease()];
        }

        return [];
    }

    public function handle(ThemeTemplateInjecting|WebhookReceived $event): void
    {
        if ($event instanceof ThemeTemplateInjecting) {
            $this->handleThemeTemplateInjection($event);
        }

        if ($event instanceof WebhookReceived) {
            $this->handleThemePublish($event);
        }
    }

    protected function handleThemeTemplateInjection(ThemeTemplateInjecting $event): void
    {
        $tenant = Tenant::find($event->tenantId);

        if (!($tenant instanceof Tenant)) {
            return;
        }

        /** @var array{domain?: string, access_token?: string} $configuration */
        $configuration = $tenant->run(function () {
            // get app setup
            $integration = Integration::where('key', 'shopify')->sole();

            $configuration = $integration->internals ?: [];

            return $configuration;
        });

        $domain = Arr::get($configuration, 'myshopify_domain');

        if (!is_not_empty_string($domain)) {
            throw new ErrorException(ErrorCode::SHOPIFY_INTEGRATION_NOT_CONNECT);
        }

        $token = Arr::get($configuration, 'access_token');

        if (!is_not_empty_string($token)) {
            throw new ErrorException(ErrorCode::SHOPIFY_INTEGRATION_NOT_CONNECT);
        }

        $this->app->setShop($domain);

        $this->app->setAccessToken($token);

        $this->injectThemeTemplate($this->app, $event->tenantId, $domain);
    }

    protected function handleThemePublish(WebhookReceived $event): void
    {
        $themeId = $event->payload['id'];

        tenancy()->runForMultiple(
            $event->tenantIds,
            function (Tenant $tenant) use ($themeId) {
                // get app setup
                $integration = Integration::where('key', 'shopify')->sole();

                $configuration = $integration->internals ?: [];

                $domain = Arr::get($configuration, 'myshopify_domain');

                if (!is_not_empty_string($domain)) {
                    return;
                }

                $token = Arr::get($configuration, 'access_token');

                if (!is_not_empty_string($token)) {
                    return;
                }

                $this->app->setShop($domain);

                $this->app->setAccessToken($token);

                $this->injectThemeTemplate($this->app, $tenant->id, $domain, $themeId);
            },
        );
    }
}
