<?php

namespace App\Console\Commands;

use App\Events\Entity\Subscription\SubscriptionPlanChanged;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;
use Laravel\Cashier\Cashier;
use Stripe\Exception\ApiErrorException;
use Stripe\Subscription;
use Stripe\SubscriptionItem;
use Webmozart\Assert\Assert;

class SyncUserSubscriptions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:subscription:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync user subscription from stripe';

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
        $stripe = Cashier::stripe();

        /** @var LazyCollection<int, User> $users */
        $users = User::whereNotNull('stripe_id')->lazyById();

        foreach ($users as $user) {
            $customer = $stripe->customers->retrieve(
                $user->stripe_id, // @phpstan-ignore-line
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

            $subscription = $user->subscription();

            if (empty($actives)) {
                if ($user->subscribed() && $subscription) {
                    // stripe does not have any active subscriptions,
                    // thus, we delete local active subscription
                    $subscription->markAsCanceled();

                    SubscriptionPlanChanged::dispatch($user->id, 'free');
                }

                continue;
            }

            Assert::count($actives, 1, sprintf('Too many active subscriptions, %s.', $customer->id));

            $active = $actives[0];

            if ($subscription === null) {
                // local does not have subscription
                $this->createLocalSubscription($user, $active);
            } elseif ($subscription->stripe_id !== $active->id) {
                // local and stripe is different subscription
                $subscription->markAsCanceled();

                $this->createLocalSubscription($user, $active);
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

                SubscriptionPlanChanged::dispatch(
                    $user->id,
                    Str::before($item->price->id, '-'),
                );
            }
        }

        return 0;
    }

    protected function createLocalSubscription(User $user, Subscription $stripeSubscription): void
    {
        Assert::count($stripeSubscription->items->data, 1, sprintf('Too many subscription items, %s.', $stripeSubscription->id));

        /** @var SubscriptionItem $item */
        $item = $stripeSubscription->items->data[0];

        /** @var \Laravel\Cashier\Subscription $subscription */
        $subscription = $user->subscriptions()->create([
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

        SubscriptionPlanChanged::dispatch(
            $user->id,
            Str::before($item->price->id, '-'),
        );
    }

    protected function timestampToCarbon(?int $timestamp): ?Carbon
    {
        if ($timestamp === null) {
            return null;
        }

        return Carbon::createFromTimestampUTC($timestamp);
    }
}
