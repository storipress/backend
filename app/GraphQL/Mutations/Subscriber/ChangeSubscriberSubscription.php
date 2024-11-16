<?php

namespace App\GraphQL\Mutations\Subscriber;

use App\Exceptions\Billing\InvalidPriceIdException;
use App\Exceptions\Billing\NoActiveSubscriptionException;
use App\Exceptions\ErrorCode;
use App\Exceptions\HttpException;
use App\Models\Tenants\Subscriber;
use Laravel\Cashier\Exceptions\IncompletePayment;
use Laravel\Cashier\Exceptions\SubscriptionUpdateFailure;
use Laravel\Cashier\Subscription;

class ChangeSubscriberSubscription
{
    use StripeTrait;

    /**
     * @param  array{
     *   price_id: string,
     * }  $args
     *
     * @throws SubscriptionUpdateFailure
     * @throws IncompletePayment
     */
    public function __invoke($_, array $args): bool
    {
        $prices = [
            tenant('stripe_monthly_price_id'),
            tenant('stripe_yearly_price_id'),
        ];

        $priceId = $args['price_id'];

        if (! in_array($priceId, $prices, true)) {
            throw new InvalidPriceIdException();
        }

        /** @var Subscriber $subscriber */
        $subscriber = Subscriber::find(
            auth()->id(),
        );

        if (! $subscriber->subscribed()) {
            throw new NoActiveSubscriptionException();
        }

        if ($subscriber->subscribed('manual')) {
            throw new HttpException(ErrorCode::MEMBER_MANUAL_SUBSCRIPTION_CONFLICT);
        }

        /** @var Subscription $subscription */
        $subscription = $subscriber->subscription();

        $customer = $subscriber->asStripeCustomer(['subscriptions']);

        if (! $customer->subscriptions || $customer->subscriptions->isEmpty()) {
            throw new NoActiveSubscriptionException();
        }

        if ($subscription->stripe_price === $priceId) {
            throw new InvalidPriceIdException();
        }

        return $this->wrapCashierConfigForSubscriber(
            fn () => $subscription->swap($priceId)->active(),
        );
    }
}
