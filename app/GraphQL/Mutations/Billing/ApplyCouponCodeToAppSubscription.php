<?php

namespace App\GraphQL\Mutations\Billing;

use App\Exceptions\Billing\InvalidPromotionCodeException;
use App\Exceptions\Billing\NoActiveSubscriptionException;
use App\Exceptions\Billing\PartnerScopeException;
use App\Exceptions\ErrorCode;
use App\Exceptions\HttpException;
use App\Models\User;
use App\Models\UserActivity;
use Laravel\Cashier\PromotionCode;
use Laravel\Cashier\Subscription;
use Segment\Segment;

class ApplyCouponCodeToAppSubscription extends BillingMutation
{
    /**
     * @param  array{
     *    promotion_code: string,
     * }  $args
     *
     * @link https://laravel.com/docs/billing#coupons
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

        $promotion = $user->findActivePromotionCode(
            $args['promotion_code'],
        );

        if (! ($promotion instanceof PromotionCode)) {
            throw new InvalidPromotionCodeException();
        }

        $subscription->applyPromotionCode(
            $promotion->asStripePromotionCode()->id,
        );

        UserActivity::log(
            name: 'billing.subscription.coupon.apply',
            subject: $subscription,
            data: [
                'promotion_code' => $args['promotion_code'],
            ],
        );

        Segment::track([
            'userId' => (string) $user->id,
            'event' => 'user_coupon_code_applied',
            'properties' => [
                'type' => 'stripe',
                'subscription_id' => $subscription->id,
                'partner_id' => $subscription->asStripeSubscription()->id,
                'plan_id' => $subscription->stripe_price,
                'promotion_code_id' => $promotion->asStripePromotionCode()->id,
                'coupon_code_id' => $promotion->coupon()->asStripeCoupon()->id,
            ],
        ]);

        return true;
    }
}
