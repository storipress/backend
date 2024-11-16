<?php

namespace App\GraphQL\Mutations\Billing;

use App\Exceptions\Billing\NoActiveSubscriptionException;
use App\Exceptions\Billing\PartnerScopeException;
use App\Exceptions\Billing\SubscriptionInGracePeriodException;
use App\Exceptions\Billing\SubscriptionNotSupportQuantityException;
use App\Exceptions\ErrorCode;
use App\Exceptions\HttpException;
use App\Models\User;
use App\Models\UserActivity;
use Laravel\Cashier\Exceptions\SubscriptionUpdateFailure;
use Laravel\Cashier\Subscription;
use Segment\Segment;
use Stripe\StripeObject;

class UpdateAppSubscriptionQuantity extends BillingMutation
{
    /**
     * @param  array{
     *     quantity: int
     * } $args
     *
     * @throws SubscriptionUpdateFailure
     *
     * @link https://laravel.com/docs/billing#subscription-quantity
     * @link https://stripe.com/docs/products-prices/pricing-models#usage-based-pricing
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

        if ($subscription->onGracePeriod()) {
            throw new SubscriptionInGracePeriodException();
        }

        $recurring = $subscription->asStripeSubscription()
            ->items
            ->first()
            ?->price
            ?->recurring;

        if (! ($recurring instanceof StripeObject)) {
            throw new SubscriptionNotSupportQuantityException();
        }

        if ($recurring['usage_type'] === 'metered') {
            throw new SubscriptionNotSupportQuantityException();
        }

        $origin = $subscription->quantity;

        if ($subscription->quantity === $args['quantity']) {
            return true;
        }

        $subscription->updateQuantity($args['quantity']);

        UserActivity::log(
            name: 'billing.subscription.quantity.change',
            subject: $subscription,
        );

        Segment::track([
            'userId' => (string) $user->id,
            'event' => 'user_subscription_quantity_updated',
            'properties' => [
                'type' => 'stripe',
                'subscription_id' => $subscription->id,
                'partner_id' => $subscription->asStripeSubscription()->id,
                'plan_id' => $subscription->stripe_price,
                'old' => $origin,
                'new' => $subscription->quantity,
            ],
        ]);

        return true;
    }
}
