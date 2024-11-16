<?php

namespace App\GraphQL\Mutations\Billing;

use App\Exceptions\Billing\NoActiveSubscriptionException;
use App\Exceptions\Billing\NoGracePeriodSubscriptionException;
use App\Exceptions\Billing\PartnerScopeException;
use App\Exceptions\ErrorCode;
use App\Exceptions\HttpException;
use App\Models\User;
use App\Models\UserActivity;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Laravel\Cashier\Subscription;
use Segment\Segment;
use Stripe\Exception\ApiErrorException;
use Webmozart\Assert\Assert;

class ResumeAppSubscription extends BillingMutation
{
    /**
     * @param  array{}  $args
     *
     * @throws ApiErrorException
     *
     * @link https://laravel.com/docs/billing#resuming-subscriptions
     */
    public function __invoke($_, array $args): bool
    {
        $user = auth()->user();

        if (! ($user instanceof User)) {
            throw new HttpException(ErrorCode::PERMISSION_FORBIDDEN);
        }

        if (! $user->subscribed()) {
            throw new NoActiveSubscriptionException();
        }

        $subscription = $user->subscription();

        if (! ($subscription instanceof Subscription)) {
            throw new NoActiveSubscriptionException();
        }

        if ($subscription->name === 'appsumo') {
            throw new PartnerScopeException();
        }

        $customer = $user->asStripeCustomer(['subscriptions']);

        if (! $customer->subscriptions || $customer->subscriptions->isEmpty()) {
            throw new NoActiveSubscriptionException();
        }

        if (! $subscription->onGracePeriod()) {
            throw new NoGracePeriodSubscriptionException();
        }

        $active = $subscription->resume()->active();

        if (Str::endsWith($subscription->stripe_price ?: '', '-trial')) {
            $price = Arr::first(
                $this->priceIds(),
                fn (string $key) => Str::startsWith($key, 'publisher-') && Str::endsWith($key, '-monthly'),
            );

            Assert::stringNotEmpty($price);

            $schedules = $user->stripe()->subscriptionSchedules;

            $schedule = $schedules->create([
                'from_subscription' => $subscription->stripe_id,
            ]);

            $schedules->update($schedule->id, [
                'phases' => [
                    array_filter($schedule->phases[0]->toArray()),
                    [
                        'items' => [
                            [
                                'price' => $price,
                            ],
                        ],
                    ],
                ],
            ]);
        }

        UserActivity::log(
            name: 'billing.subscription.resume',
            subject: $subscription,
        );

        Segment::track([
            'userId' => (string) $user->id,
            'event' => 'user_subscription_resumed',
            'properties' => [
                'type' => 'stripe',
                'subscription_id' => $subscription->id,
                'partner_id' => $subscription->asStripeSubscription()->id,
                'plan_id' => $subscription->stripe_price,
            ],
        ]);

        return $active;
    }
}
