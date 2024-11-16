<?php

namespace App\Console\Commands\Subscriber;

use App\Models\Tenant;
use App\Models\Tenants\Subscriber;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\LazyCollection;
use Stripe\Exception\ApiErrorException;
use Stripe\Subscription;
use Stripe\SubscriptionItem;
use Webmozart\Assert\Assert;

class SyncSubscriberSubscriptions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriber:subscription:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync subscriber subscription from stripe';

    /**
     * Execute the console command.
     *
     *
     * @throws ApiErrorException
     *
     * @link https://stripe.com/docs/api/expanding_objects
     * @link https://stripe.com/docs/api/subscriptions
     * @link https://stripe.com/docs/api/subscription_items
     */
    public function handle(): int
    {
        tenancy()->runForMultiple(null, function (Tenant $tenant) {
            if (! $tenant->initialized) {
                return;
            }

            if (! $tenant->stripe_account_id) {
                return;
            }

            $stripe = Subscriber::stripe();

            if ($stripe === null) {
                return;
            }

            /** @var LazyCollection<int, Subscriber> $subscribers */
            $subscribers = Subscriber::whereNotNull('stripe_id')->lazyById();

            foreach ($subscribers as $subscriber) {
                if (! is_string($subscriber->stripe_id)) {
                    continue;
                }

                $customer = $stripe->customers->retrieve(
                    $subscriber->stripe_id,
                    ['expand' => ['subscriptions']],
                );

                /** @var Subscription[] $subscriptions */
                $subscriptions = $customer->subscriptions?->data ?: [];

                // only get active subscriptions
                $actives = array_filter(
                    $subscriptions,
                    fn ($subscription) => in_array($subscription->status, ['active', 'trialing'], true),
                );

                $actives = array_values($actives);

                $subscription = $subscriber->subscription();

                if (empty($actives)) {
                    if ($subscriber->subscribed() && $subscription) {
                        // stripe does not have any active subscriptions,
                        // thus, we delete local active subscription
                        $subscription->markAsCanceled();
                    }

                    $subscriber->update(['subscribed_at' => null]);

                    continue;
                }

                Assert::count($actives, 1, sprintf('Too many active subscriptions, %s.', $customer->id));

                $active = $actives[0];

                if ($subscription === null) {
                    // local does not have subscription
                    $this->createLocalSubscription($subscriber, $active);
                } elseif ($subscription->stripe_id !== $active->id) {
                    // local and stripe is different subscription
                    $subscription->markAsCanceled();

                    $this->createLocalSubscription($subscriber, $active);
                } else {
                    // local and stripe is same subscription
                    Assert::count($active->items->data, 1, sprintf('Too many subscription items, %s.', $active->id));

                    /** @var SubscriptionItem $item */
                    $item = $active->items->data[0];

                    $subscription->update([
                        'stripe_status' => $active->status,
                        'stripe_price' => $item->price->id,
                        'quantity' => $item->quantity ?? null,
                        'trial_ends_at' => $this->timestampToCarbon($active->trial_end),
                        'ends_at' => $this->timestampToCarbon($active->ended_at),
                    ]);

                    if ($subscription->items()->count() > 1) {
                        // subscription will only contain exactly 1 item, if there
                        // are more than 1 item, that means it is out of date, we
                        // will delete all of them
                        $subscription->items()->delete();
                    }

                    $subscription->items()
                        ->firstOrNew()
                        ->fill([
                            'stripe_id' => $item->id,
                            'stripe_product' => $item->price->product,
                            'stripe_price' => $item->price->id,
                            'quantity' => $item->quantity ?? null,
                        ])
                        ->save();
                }

                $startedAt = $this->timestampToCarbon($active->start_date);

                $subscriber->update([
                    'first_paid_at' => $subscriber->first_paid_at ?: $startedAt,
                    'subscribed_at' => $startedAt,
                ]);
            }
        });

        return 0;
    }

    protected function createLocalSubscription(Subscriber $subscriber, Subscription $stripeSubscription): void
    {
        Assert::count($stripeSubscription->items->data, 1, sprintf('Too many subscription items, %s.', $stripeSubscription->id));

        /** @var SubscriptionItem $item */
        $item = $stripeSubscription->items->data[0];

        /** @var \Laravel\Cashier\Subscription $subscription */
        $subscription = $subscriber->subscriptions()->create([
            'name' => 'default',
            'stripe_id' => $stripeSubscription->id,
            'stripe_status' => $stripeSubscription->status,
            'stripe_price' => $item->price->id,
            'quantity' => $item->quantity ?? null,
            'trial_ends_at' => $this->timestampToCarbon($stripeSubscription->trial_end),
            'ends_at' => $this->timestampToCarbon($stripeSubscription->ended_at),
        ]);

        $subscription->items()->create([
            'stripe_id' => $item->id,
            'stripe_product' => $item->price->product,
            'stripe_price' => $item->price->id,
            'quantity' => $item->quantity ?? null,
        ]);
    }

    protected function timestampToCarbon(?int $timestamp): ?Carbon
    {
        if ($timestamp === null) {
            return null;
        }

        return Carbon::createFromTimestampUTC($timestamp);
    }
}
