<?php

namespace App\GraphQL\Mutations\Billing;

use App\Exceptions\Billing\NoActiveSubscriptionException;
use App\Exceptions\Billing\PartnerScopeException;
use App\Exceptions\Billing\SubscriptionInGracePeriodException;
use App\Exceptions\ErrorCode;
use App\Exceptions\HttpException;
use App\Models\User;
use App\Models\UserActivity;
use Laravel\Cashier\Subscription;
use Segment\Segment;
use Stripe\Exception\ApiErrorException;
use Stripe\SubscriptionSchedule;

class CancelAppSubscription extends BillingMutation
{
    /**
     * @param  array{}  $args
     *
     * @throws ApiErrorException
     *
     * @link https://laravel.com/docs/billing#cancelling-subscriptions
     */
    public function __invoke($_, array $args): bool
    {
        $user = auth()->user();

        if (!($user instanceof User)) {
            throw new HttpException(ErrorCode::PERMISSION_FORBIDDEN);
        }

        if (!$user->subscribed()) {
            throw new NoActiveSubscriptionException();
        }

        $subscription = $user->subscription();

        if (!($subscription instanceof Subscription)) {
            throw new NoActiveSubscriptionException();
        }

        if ($subscription->name === 'appsumo') {
            throw new PartnerScopeException();
        }

        $customer = $user->asStripeCustomer(['subscriptions']);

        if (!$customer->subscriptions || $customer->subscriptions->isEmpty()) {
            throw new NoActiveSubscriptionException();
        }

        if ($subscription->onGracePeriod()) {
            throw new SubscriptionInGracePeriodException();
        }

        $stripeSubscription = $subscription->asStripeSubscription(['schedule']);

        if ($stripeSubscription->schedule instanceof SubscriptionSchedule) {
            $stripeSubscription->schedule->release();
        }

        $onGracePeriod = $subscription->cancel()->onGracePeriod();

        UserActivity::log(
            name: 'billing.subscription.cancel',
            subject: $subscription,
        );

        Segment::track([
            'userId' => (string) $user->id,
            'event' => 'user_subscription_canceled',
            'properties' => [
                'type' => 'stripe',
                'subscription_id' => $subscription->id,
                'partner_id' => $subscription->asStripeSubscription()->id,
                'plan_id' => $subscription->stripe_price,
            ],
        ]);

        return $onGracePeriod;
    }
}
