<?php

namespace App\Listeners\Partners\Shopify\OAuthConnected;

use App\Events\Partners\Shopify\OAuthConnected;
use App\Models\Tenant;
use App\Models\Tenants\Integration;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

use function Sentry\captureException;

class SetupIntegration implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(OAuthConnected $event): void
    {
        $tenant = Tenant::findOrFail($event->tenantId);

        $tenant->run(function () use ($event) {
            try {
                $shopify = Integration::findOrFail('shopify');

                $shopify->update([
                    'data' => [
                        'id' => $event->shop->id,
                        'name' => $event->shop->name,
                        'domain' => $event->shop->domain,
                        'myshopify_domain' => $event->shop->myshopifyDomain,
                        'prefix' => '/a/blog',
                    ],
                    'internals' => [
                        'id' => $event->shop->id,
                        'name' => $event->shop->name,
                        'domain' => $event->shop->domain,
                        'myshopify_domain' => $event->shop->myshopifyDomain,
                        'prefix' => '/a/blog',
                        'email' => $event->shop->email,
                        'access_token' => $event->token,
                        'scopes' => $event->scopes,
                    ],
                ]);
            } catch (Throwable $e) {
                captureException($e);
            }
        });
    }
}
