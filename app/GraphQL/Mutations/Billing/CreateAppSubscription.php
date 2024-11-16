<?php

namespace App\GraphQL\Mutations\Billing;

use App\Exceptions\Billing\CustomerNotExistsException;
use App\Exceptions\Billing\InvalidBillingAddressException;
use App\Exceptions\Billing\InvalidPriceIdException;
use App\Exceptions\Billing\InvalidPromotionCodeException;
use App\Exceptions\Billing\PaymentNotSetException;
use App\Exceptions\Billing\SubscriptionExistsException;
use App\Exceptions\ErrorCode;
use App\Exceptions\HttpException;
use App\Models\User;
use App\Models\UserActivity;
use Illuminate\Support\Str;
use Laravel\Cashier\Exceptions\IncompletePayment;
use Segment\Segment;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\CardException;
use Stripe\Exception\InvalidRequestException;

use function Sentry\captureException;

class CreateAppSubscription extends BillingMutation
{
    /**
     * @param  array{
     *     price_id: string,
     *     quantity: int,
     *     promotion_code?: string
     * } $args
     *
     * @throws IncompletePayment
     * @throws InvalidRequestException
     * @throws ApiErrorException
     *
     * @link https://laravel.com/docs/billing#creating-subscriptions
     * @link https://laravel.com/docs/billing#coupons
     * @link https://stripe.com/docs/products-prices/overview
     * @link https://stripe.com/docs/expand
     * @link https://stripe.com/docs/sources/customers
     */
    public function __invoke($_, array $args): bool
    {
        $user = auth()->user();

        if (! ($user instanceof User)) {
            throw new HttpException(ErrorCode::PERMISSION_FORBIDDEN);
        }

        $priceIds = $this->priceIds();

        if (! in_array($args['price_id'], $priceIds, true)) {
            throw new InvalidPriceIdException();
        }

        if (! $user->hasStripeId()) {
            throw new CustomerNotExistsException();
        }

        if ($user->subscribed()) {
            throw new SubscriptionExistsException();
        }

        $customer = $user->asStripeCustomer(['subscriptions', 'sources']);

        if ((! $customer->sources || $customer->sources->isEmpty()) && $user->paymentMethods()->isEmpty()) {
            throw new PaymentNotSetException();
        }

        if ($customer->subscriptions && ! $customer->subscriptions->isEmpty()) {
            throw new SubscriptionExistsException();
        }

        // If the user tries to use features from the PLUS plan without actually selecting
        // the PLUS plan, the API will throw an InvalidPriceIdException error.
        if ($this->isUsingPlusFeature($user)) {
            if (! Str::contains($args['price_id'], 'publisher-')) {
                throw new InvalidPriceIdException();
            }
        }

        $builder = $user->newSubscription('default')
            ->price($args['price_id'], $args['quantity'])
            ->anchorBillingCycleOn(
                now()->addMonthNoOverflow()->day(5)->startOfDay(),
            )
            ->errorIfPaymentFails();

        if (! empty($args['promotion_code'])) {
            $promotion = $user->findActivePromotionCode(
                $args['promotion_code'],
            );

            if ($promotion === null) {
                throw new InvalidPromotionCodeException();
            }

            $builder->withPromotionCode(
                $promotion->asStripePromotionCode()->id,
            );
        }

        try {
            $subscription = $builder->create();

            UserActivity::log(
                name: 'billing.subscription.create',
                subject: $subscription,
            );

            Segment::track([
                'userId' => (string) $user->id,
                'event' => 'user_subscription_created',
                'properties' => [
                    'type' => 'stripe',
                    'subscription_id' => $subscription->id,
                    'partner_id' => $subscription->asStripeSubscription()->id,
                    'plan_id' => $subscription->stripe_price,
                ],
            ]);

            return $subscription->active();
        } catch (CardException|IncompletePayment) {
            return false;
        } catch (InvalidRequestException $e) {
            if (Str::contains($e->getMessage(), 'location isn\'t recognized')) {
                throw new InvalidBillingAddressException();
            }

            captureException($e);

            return false;
        }
    }
}
