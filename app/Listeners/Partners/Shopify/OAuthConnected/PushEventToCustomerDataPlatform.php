<?php

namespace App\Listeners\Partners\Shopify\OAuthConnected;

use App\Events\Partners\Shopify\OAuthConnected;
use App\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Segment\Segment;

class PushEventToCustomerDataPlatform implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(OAuthConnected $event): void
    {
        $tenant = Tenant::withoutEagerLoads()
            ->with(['owner'])
            ->find($event->tenantId);

        if (!($tenant instanceof Tenant)) {
            return;
        }

        Segment::track([
            'userId' => (string) $tenant->owner->id,
            'event' => 'shopify_connected',
            'properties' => [
                'tenant_uid' => $tenant->id,
                'tenant_name' => $tenant->name,
                'store_id' => $event->shop->id,
                'domain' => $event->shop->domain,
                'shopify_domain' => $event->shop->myshopifyDomain,
            ],
            'context' => [
                'groupId' => $tenant->id,
            ],
        ]);
    }
}
