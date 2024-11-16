<?php

namespace App\Listeners\Partners\Shopify\OAuthConnected;

use App\Builder\ReleaseEventsBuilder;
use App\Events\Partners\Shopify\OAuthConnected;
use App\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SetupPublication implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(OAuthConnected $event): void
    {
        $tenant = Tenant::findOrFail($event->tenantId);

        $tenant->shopify_data = [
            'id' => $event->shop->id,
            'domain' => $event->shop->domain,
            'myshopify_domain' => $event->shop->myshopifyDomain,
        ];

        $tenant->custom_site_template_path = 'assets/storipress/templates/shopify.zip';

        $tenant->custom_site_template = true;

        $tenant->save();

        $tenant->run(
            fn () => (new ReleaseEventsBuilder())->handle('shopify:enabled'),
        );
    }
}
