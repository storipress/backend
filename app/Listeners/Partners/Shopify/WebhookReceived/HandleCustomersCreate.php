<?php

namespace App\Listeners\Partners\Shopify\WebhookReceived;

use App\Events\Partners\Shopify\WebhookReceived;
use App\Models\Tenant;
use App\Resources\Partners\Shopify\Customer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class HandleCustomersCreate implements ShouldQueue
{
    use InteractsWithQueue;
    use WebhookHelper;

    /**
     * Determine whether the listener should be queued.
     */
    public function shouldQueue(WebhookReceived $event): bool
    {
        return $event->topic === 'customers/create' &&
               ! empty($event->payload['email']);
    }

    /**
     * Handle the event.
     *
     * @see https://shopify.dev/docs/api/admin-rest/2023-01/resources/webhook#event-topics-customers-create
     */
    public function handle(WebhookReceived $event): void
    {
        $customer = new Customer(
            id: $event->payload['id'],
            email: $event->payload['email'],
            firstName: $event->payload['first_name'],
            lastName: $event->payload['last_name'],
            acceptsMarketing: $event->payload['accepts_marketing'],
            verifiedEmail: $event->payload['verified_email'] ?? false,
        );

        tenancy()->runForMultiple(
            $event->tenantIds,
            function (Tenant $tenant) use ($customer) {
                if (! $this->isCustomerSyncingEnabled()) {
                    return;
                }

                $subscriber = $this->updateOrCreateSubscriber($customer);

                $subscriber->tenants()->sync($tenant, false);

                $this->updateOrCreateTenantSubscriber($subscriber->id, $customer);
            },
        );
    }
}
