<?php

namespace App\GraphQL\Mutations\Billing;

use App\Enums\Credit\State as CreditState;
use App\Exceptions\Billing\InvalidPriceIdException;
use App\Exceptions\Billing\InvalidPromotionCodeException;
use App\Exceptions\Billing\NoActiveSubscriptionException;
use App\Exceptions\Billing\PartnerScopeException;
use App\Exceptions\ErrorCode;
use App\Exceptions\HttpException;
use App\Models\User;
use Laravel\Cashier\Subscription;
use Laravel\Cashier\SubscriptionItem;
use Stripe\Exception\ApiErrorException;
use Stripe\Invoice;
use Stripe\SubscriptionSchedule;
use Webmozart\Assert\Assert;

class PreviewAppSubscription extends BillingMutation
{
    /**
     * @param  array{
     *     price_id: string,
     *     quantity: int,
     *     promotion_code?: string,
     * }  $args
     * @return array{
     *     credit: int,
     *     discount: int,
     *     subtotal: int,
     *     tax: int,
     *     total: int,
     * }
     *
     * @throws ApiErrorException
     */
    public function __invoke($_, array $args): array
    {
        $user = auth()->user();

        if (! ($user instanceof User)) {
            throw new HttpException(ErrorCode::PERMISSION_FORBIDDEN);
        }

        $priceIds = $this->priceIds();

        if (! in_array($args['price_id'], $priceIds, true)) {
            throw new InvalidPriceIdException();
        }

        $subscribed = $user->subscribed();

        if (! empty($args['promotion_code'])) {
            $promotion = $user->findActivePromotionCode(
                $args['promotion_code'],
            );

            if ($promotion === null) {
                throw new InvalidPromotionCodeException();
            }

            $couponId = $promotion->coupon()->asStripeCoupon()->id;
        }

        $customer = $user->asStripeCustomer(['subscriptions']);

        if ($subscribed && (! $customer->subscriptions || $customer->subscriptions->isEmpty())) {
            $subscribed = false;
        }

        if ($subscribed) {
            $subscription = $user->subscription();

            if (! ($subscription instanceof Subscription)) {
                throw new NoActiveSubscriptionException();
            }

            if ($subscription->name === 'appsumo') {
                throw new PartnerScopeException();
            }

            if ($subscription->stripe_price === $args['price_id']) {
                if ($subscription->quantity === $args['quantity']) {
                    throw new InvalidPriceIdException();
                }
            }

            $subscriptionId = $subscription->stripe_id;

            $item = $subscription->items->first();

            Assert::isInstanceOf($item, SubscriptionItem::class);

            $existing = [
                'id' => $item->stripe_id,
                'deleted' => true,
            ];

            $stripeSubscription = $subscription->asStripeSubscription(['schedule']);
        }

        $plan = [
            'price' => $args['price_id'],
            'quantity' => $args['quantity'],
        ];

        $options = [
            'subscription' => $subscriptionId ?? null,
            'subscription_cancel_at_period_end' => $subscribed,
            'subscription_items' => array_values(
                array_filter([
                    $existing ?? [],
                    $plan,
                ]),
            ),
            'subscription_trial_end' => time() - 1,
            'coupon' => $couponId ?? null,
        ];

        if (($stripeSubscription ?? null)?->schedule instanceof SubscriptionSchedule) {
            $options = [
                'subscription_items' => [$plan],
                'coupon' => $couponId ?? null,
            ];
        }

        $invoice = $user->upcomingInvoice(array_filter($options))?->asStripeInvoice();

        Assert::isInstanceOf($invoice, Invoice::class);

        $credits = $user->credits()
            ->where('state', '=', CreditState::available())
            ->sum('amount');

        Assert::integerish($credits);

        $credits = intval($credits);

        return [
            'credit' => $credits,
            'discount' => intval(array_sum(array_column($invoice->total_discount_amounts ?: [], 'amount'))),
            'subtotal' => max($invoice->subtotal, 0),
            'tax' => max($invoice->tax ?: 0, 0),
            'total' => max($invoice->total - $credits, 0),
        ];
    }
}
