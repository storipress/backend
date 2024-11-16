<?php

namespace App\GraphQL\Mutations\Billing;

use App\Exceptions\Billing\InvalidPriceIdException;
use App\Exceptions\Billing\InvalidPromotionCodeException;
use App\Exceptions\Billing\NoActiveSubscriptionException;
use App\Exceptions\Billing\PartnerScopeException;
use App\Exceptions\ErrorCode;
use App\Exceptions\HttpException;
use App\Models\User;
use App\Models\UserActivity;
use Illuminate\Support\Str;
use Laravel\Cashier\Exceptions\IncompletePayment;
use Laravel\Cashier\Exceptions\SubscriptionUpdateFailure;
use Laravel\Cashier\Subscription;
use Segment\Segment;
use Stripe\Exception\ApiErrorException;
use Stripe\SubscriptionSchedule;

class SwapAppSubscription extends BillingMutation
{
    /**
     * @param array{
     *    price_id: string,
     *    quantity: int,
     *    promotion_code?: string,
     * } $args
     *
     * @throws IncompletePayment
     * @throws SubscriptionUpdateFailure
     * @throws ApiErrorException
     *
     * @link https://laravel.com/docs/billing#changing-prices
     */
    public function __invoke($_, array $args): bool
    {
        $user = auth()->user();

        if (! ($user instanceof User)) {
            throw new HttpException(ErrorCode::PERMISSION_FORBIDDEN);
        }

        $priceIds = $this->priceIds();

        $priceId = $args['price_id'];

        if (! in_array($priceId, $priceIds, true)) {
            throw new InvalidPriceIdException();
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

        $origin = $subscription->stripe_price;

        if ($origin === null) {
            throw new NoActiveSubscriptionException();
        }

        if (Str::contains($origin, 'publisher-')) {
            if ($this->isUsingPlusFeature($user)) {
                if (Str::contains($priceId, 'blogger-')) {
                    throw new InvalidPriceIdException();
                }
            }
        }

        if ($origin === $priceId) {
            throw new InvalidPriceIdException();
        }

        if (Str::endsWith($subscription->stripe_price ?: '', '-trial')) {
            $schedules = $user->stripe()->subscriptionSchedules;

            $stripeSubscription = $subscription->asStripeSubscription(['schedule']);

            $schedule = $stripeSubscription->schedule;

            if (! ($schedule instanceof SubscriptionSchedule)) {
                $schedule = $schedules->create([
                    'from_subscription' => $stripeSubscription->id,
                ]);
            }

            $schedules->update($schedule->id, [
                'phases' => [
                    array_filter($schedule->phases[0]->toArray()),
                    [
                        'items' => [
                            [
                                'price' => $priceId,
                                'quantity' => $args['quantity'],
                            ],
                        ],
                    ],
                ],
            ]);

            return true;
        }

        $stripeSubscription = $subscription->asStripeSubscription(['schedule']);

        if ($stripeSubscription->schedule instanceof SubscriptionSchedule) {
            $stripeSubscription->schedule->release();
        }

        $options = [];

        if (! empty($args['promotion_code'])) {
            $promotion = $user->findActivePromotionCode(
                $args['promotion_code'],
            );

            if ($promotion === null) {
                throw new InvalidPromotionCodeException();
            }

            $options['promotion_code'] = $promotion->asStripePromotionCode()->id;
        }

        $params = [
            $priceId => [
                'quantity' => $args['quantity'],
            ],
        ];

        $active = $subscription->swap($params, $options)->active();

        UserActivity::log(
            name: 'billing.subscription.swap',
            subject: $subscription,
        );

        Segment::track([
            'userId' => (string) $user->id,
            'event' => 'user_subscription_plan_changed',
            'properties' => [
                'type' => 'stripe',
                'subscription_id' => $subscription->id,
                'partner_id' => $subscription->asStripeSubscription()->id,
                'old' => $origin,
                'new' => $subscription->stripe_price,
                'quantity' => $subscription->quantity,
            ],
        ]);

        return $active;
    }
}
