<?php

namespace App\Listeners\Partners\Shopify\WebhookReceived;

use App\Events\Partners\Shopify\WebhookReceived;
use App\Models\Tenants\Subscriber as TenantSubscriber;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class HandleCustomersDelete implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Determine whether the listener should be queued.
     */
    public function shouldQueue(WebhookReceived $event): bool
    {
        return $event->topic === 'customers/delete';
    }

    /**
     * Handle the event.
     *
     * @see https://shopify.dev/docs/api/admin-rest/2023-01/resources/webhook#event-topics-customers-delete
     */
    public function handle(WebhookReceived $event): void
    {
        tenancy()->runForMultiple(
            $event->tenantIds,
            fn () => TenantSubscriber::where(
                'shopify_id',
                '=',
                $event->payload['id'],
            )->update([
                'shopify_id' => null,
                'newsletter' => false,
            ]),
        );
    }
}
