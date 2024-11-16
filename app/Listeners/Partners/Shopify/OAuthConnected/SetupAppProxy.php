<?php

namespace App\Listeners\Partners\Shopify\OAuthConnected;

use App\Events\Partners\Shopify\OAuthConnected;
use App\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Webmozart\Assert\Assert;

class SetupAppProxy implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(OAuthConnected $event): void
    {
        $namespace = config('services.cloudflare.kv.shopify_app_proxy');

        Assert::stringNotEmpty($namespace);

        $tenant = Tenant::findOrFail($event->tenantId);

        app('cloudflare')->setKVKeys($namespace, [
            [
                'key' => $event->shop->domain,
                'value' => $tenant->cf_pages_domain,
            ],
            [
                'key' => $event->shop->myshopifyDomain,
                'value' => $tenant->cf_pages_domain,
            ],
        ]);
    }
}
