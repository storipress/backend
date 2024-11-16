<?php

namespace App\GraphQL\Mutations\Subscriber;

use App\Exceptions\Billing\NoActiveSubscriptionException;
use App\Exceptions\Billing\NoGracePeriodSubscriptionException;
use App\Exceptions\ErrorCode;
use App\Exceptions\HttpException;
use App\Models\Tenants\Subscriber;
use Laravel\Cashier\Subscription;

class ResumeSubscriberSubscription
{
    use StripeTrait;

    /**
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args): bool
    {
        /** @var Subscriber $subscriber */
        $subscriber = Subscriber::find(
            auth()->id(),
        );

        if (!$subscriber->subscribed()) {
            throw new NoActiveSubscriptionException();
        }

        if ($subscriber->subscribed('manual')) {
            throw new HttpException(ErrorCode::MEMBER_MANUAL_SUBSCRIPTION_CONFLICT);
        }

        /** @var Subscription $subscription */
        $subscription = $subscriber->subscription();

        $customer = $subscriber->asStripeCustomer(['subscriptions']);

        if (!$customer->subscriptions || $customer->subscriptions->isEmpty()) {
            throw new NoActiveSubscriptionException();
        }

        if (!$subscription->onGracePeriod()) {
            throw new NoGracePeriodSubscriptionException();
        }

        return $this->wrapCashierConfigForSubscriber(
            fn () => $subscription->resume()->active(),
        );
    }
}
