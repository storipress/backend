<?php

declare(strict_types=1);

namespace App\Listeners\Partners\Webflow\OAuthConnected;

use App\Events\Partners\Webflow\OAuthConnected;
use App\Listeners\Traits\HasIngestHelper;
use App\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Subscription;
use Stripe\SubscriptionSchedule;

class UpgradePlan implements ShouldQueue
{
    use HasIngestHelper;
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(OAuthConnected $event): void
    {
        $tenant = Tenant::withoutEagerLoads()
            ->with(['owner', 'owner.publications'])
            ->initialized()
            ->find($event->tenantId);

        if (!($tenant instanceof Tenant)) {
            return;
        }

        $user = $tenant->owner;

        if (!$user->subscribed()) {
            return; // @todo - webflow this must not be occurred
        }

        $subscription = $user->subscription();

        if (!($subscription instanceof Subscription)) {
            return; // @todo - webflow this must not be occurred
        }

        if ($subscription->name === 'appsumo') {
            return;
        }

        $customer = $user->asStripeCustomer(['subscriptions']);

        if (!$customer->subscriptions || $customer->subscriptions->isEmpty()) {
            return; // @todo - webflow this must not be occurred
        }

        $schedules = Cashier::stripe()->subscriptionSchedules;

        $stripeSubscription = $subscription->asStripeSubscription(['schedule']);

        $schedule = $stripeSubscription->schedule;

        if (!($schedule instanceof SubscriptionSchedule)) {
            $schedule = $schedules->create([
                'from_subscription' => $stripeSubscription->id,
            ]);
        }

        $tenantIds = $user->publications->pluck('id')->toArray();

        $quantity = DB::table('tenant_user')
            ->whereIn('tenant_id', $tenantIds)
            ->whereIn('role', ['owner', 'admin', 'editor'])
            ->pluck('user_id')
            ->unique()
            ->count();

        $schedules->update($schedule->id, [
            'phases' => [
                array_filter($schedule->phases[0]->toArray()),
                [
                    'items' => [
                        [
                            'price' => 'publisher-3-yearly',
                            'quantity' => max($quantity, 1),
                        ],
                    ],
                ],
            ],
        ]);

        $this->ingest($event);
    }
}
