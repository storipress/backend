<?php

namespace App\Listeners\Partners\Shopify\WebhookReceived;

use App\Events\Partners\Shopify\WebhookReceived;
use App\Models\Subscriber;
use App\Models\Tenants\Subscriber as TenantSubscriber;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Arr;

class HandleCustomersRedact implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Determine whether the listener should be queued.
     */
    public function shouldQueue(WebhookReceived $event): bool
    {
        return $event->topic === 'customers/redact';
    }

    /**
     * Handle the event.
     *
     * @see https://shopify.dev/docs/apps/webhooks/configuration/mandatory-webhooks#customers-redact-payload
     */
    public function handle(WebhookReceived $event): void
    {
        $email = Arr::get($event->payload, 'customer.email');

        if (!is_not_empty_string($email)) {
            return;
        }

        $subscriber = Subscriber::where('email', '=', $email)->first();

        if ($subscriber === null) {
            return;
        }

        if ($subscriber->tenants()->count() === 1) {
            $subscriber->update([
                'first_name' => null,
                'last_name' => null,
            ]);
        }

        tenancy()->runForMultiple(
            $event->tenantIds,
            fn () => TenantSubscriber::find($subscriber->id)?->update([
                'shopify_id' => null,
                'newsletter' => false,
            ]),
        );
    }
}
