<?php

namespace App\Listeners\Partners\Shopify\WebhookReceived;

use App\Builder\ReleaseEventsBuilder;
use App\Events\Partners\Shopify\WebhookReceived;
use App\Models\Tenant;
use App\Models\Tenants\Integration;
use App\Models\Tenants\UserActivity;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class HandleAppUninstalled implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Determine whether the listener should be queued.
     */
    public function shouldQueue(WebhookReceived $event): bool
    {
        return $event->topic === 'app/uninstalled';
    }

    /**
     * Handle the event.
     *
     * @see https://shopify.dev/docs/api/admin-rest/2023-01/resources/webhook#event-topics-app-uninstalled
     */
    public function handle(WebhookReceived $event): void
    {
        tenancy()->runForMultiple(
            $event->tenantIds,
            function (Tenant $tenant) {
                Integration::find('shopify')?->revoke();

                $tenant->shopify_data = null;

                $tenant->custom_site_template_path = null;

                $tenant->custom_site_template = false;

                $tenant->save();

                $tenant->run(
                    fn () => (new ReleaseEventsBuilder())->handle('shopify:disable'),
                );

                UserActivity::log(
                    name: 'integration.disconnect',
                    data: [
                        'key' => 'shopify',
                    ],
                    userId: $tenant->owner->id,
                );
            },
        );
    }
}
