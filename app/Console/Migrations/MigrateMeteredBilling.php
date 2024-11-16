<?php

namespace App\Console\Migrations;

use App\Models\User;
use Generator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Subscription;
use Stripe\Subscription as StripeSubscription;
use Stripe\SubscriptionSchedule;

class MigrateMeteredBilling extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:metered-billing';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $priceId = app()->isProduction() ? 'publisher-2-monthly' : 'publisher-3-monthly';

        $schedules = Cashier::stripe()->subscriptionSchedules;

        foreach ($this->prices() as $price) {
            foreach ($this->subscriptions($price) as $stripeSubscription) {
                $subscription = Subscription::withoutEagerLoads()
                    ->with(['owner', 'owner.publications'])
                    ->where('stripe_id', '=', $stripeSubscription->id)
                    ->first();

                if (!($subscription instanceof Subscription) || !($subscription->owner instanceof User)) {
                    continue;
                }

                $schedule = $stripeSubscription->schedule;

                if (!($schedule instanceof SubscriptionSchedule)) {
                    $schedule = $schedules->create([
                        'from_subscription' => $stripeSubscription->id,
                    ]);
                }

                $tenantIds = $subscription->owner->publications->pluck('id')->toArray();

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
                                    'price' => $priceId,
                                    'quantity' => max($quantity, 1),
                                ],
                            ],
                        ],
                    ],
                ]);
            }
        }

        return static::SUCCESS;
    }

    /**
     * @return Generator<int, string>
     */
    public function prices(): Generator
    {
        $prices = Cashier::stripe()->prices->all([
            'type' => 'recurring',
            'recurring' => [
                'usage_type' => 'metered',
            ],
            'limit' => 100,
        ]);

        foreach ($prices->autoPagingIterator() as $price) {
            if (Str::contains($price->nickname ?: '', 'Monthly Metered')) {
                continue;
            }

            yield $price->id;
        }
    }

    /**
     * @return Generator<int, StripeSubscription>
     */
    public function subscriptions(string $price): Generator
    {
        $subscriptions = Cashier::stripe()->subscriptions->all([
            'price' => $price,
            'status' => 'active',
            'limit' => 100,
            'expand' => ['data.schedule'],
        ]);

        foreach ($subscriptions->autoPagingIterator() as $subscription) {
            yield $subscription;
        }
    }
}
