<?php

namespace App\Listeners\StripeWebhookHandled;

use App\Events\Entity\Subscription\SubscriptionPlanChanged;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Events\WebhookHandled;

class HandleSubscriptionChanged implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(WebhookHandled $event): void
    {
        $events = [
            'customer.subscription.created',
            'customer.subscription.updated',
            'customer.subscription.deleted',
        ];

        if (! in_array($event->payload['type'], $events, true)) {
            return;
        }

        $stripeId = Arr::get($event->payload, 'data.object.customer');

        if (! is_not_empty_string($stripeId)) {
            return;
        }

        /** @var User|null $user */
        $user = Cashier::findBillable($stripeId);

        if (! ($user instanceof User)) {
            return;
        }

        $subscription = $user->subscription();

        $price = $subscription?->stripe_price ?: '';

        $plan = Str::before($price, '-');

        SubscriptionPlanChanged::dispatch(
            $user->id,
            $plan ?: 'free',
        );
    }
}
