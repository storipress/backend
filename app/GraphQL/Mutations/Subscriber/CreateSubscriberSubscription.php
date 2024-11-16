<?php

namespace App\GraphQL\Mutations\Subscriber;

use App\Exceptions\BadRequestHttpException;
use App\Exceptions\Billing\InvalidPriceIdException;
use App\Exceptions\Billing\PaymentNotSetException;
use App\Exceptions\Billing\SubscriptionExistsException;
use App\Exceptions\ErrorCode;
use App\Exceptions\HttpException;
use App\Exceptions\QuotaExceededHttpException;
use App\Models\Tenant;
use App\Models\Tenants\Subscriber;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Cashier\Exceptions\IncompletePayment;
use Stripe\Exception\ApiErrorException;

class CreateSubscriberSubscription
{
    use StripeTrait;

    /**
     * @param  array{
     *   price_id: string,
     * }  $args
     *
     * @throws IncompletePayment
     * @throws ApiErrorException
     */
    public function __invoke($_, array $args): bool
    {
        $tenant = tenant();

        if (! ($tenant instanceof Tenant)) {
            throw new BadRequestHttpException();
        }

        $subscription = $tenant->owner->subscription();

        if ($subscription === null) {
            throw new QuotaExceededHttpException();
        }

        $prices = [
            tenant('stripe_monthly_price_id'),
            tenant('stripe_yearly_price_id'),
        ];

        if (! in_array($args['price_id'], $prices, true)) {
            throw new InvalidPriceIdException();
        }

        /** @var \App\Models\Subscriber $user */
        $user = auth()->user();

        /** @var Subscriber $subscriber */
        $subscriber = Subscriber::find($user->id);

        $this->ensureCustomerExists($subscriber);

        if ($subscriber->subscribed()) {
            throw new SubscriptionExistsException();
        }

        if ($subscriber->subscribed('manual')) {
            throw new HttpException(ErrorCode::MEMBER_MANUAL_SUBSCRIPTION_CONFLICT);
        }

        $customer = $subscriber->asStripeCustomer(['subscriptions', 'sources']);

        if ((! $customer->sources || $customer->sources->isEmpty()) && $subscriber->paymentMethods()->isEmpty()) {
            throw new PaymentNotSetException();
        }

        if ($customer->subscriptions && ! $customer->subscriptions->isEmpty()) {
            throw new SubscriptionExistsException();
        }

        if (! $user->verified) {
            // $key = sprintf('subscriber-pending-subscription-%d', $user->id);
            //
            // Cache::put($key, $args['price_id'], now()->addDays());
            //
            // return false;
        }

        $this->wrapCashierConfigForSubscriber(function () use ($subscription, $subscriber, $args) {
            $plan = Str::before($subscription->stripe_price ?: '', '-');

            $key = sprintf('billing.fee.%s', $plan ?: 'free');

            $fee = config($key);

            if (! is_numeric($fee)) {
                $fee = 0;
            }

            $subscriber->newSubscription('default')
                ->price($args['price_id'])
                ->create(null, [], [
                    'application_fee_percent' => max($fee, 0),
                ]);

            $subscriber->update([
                'first_paid_at' => $subscriber->first_paid_at ?: now(),
                'subscribed_at' => now(),
                'paid_up_source' => 'Direct',
            ]);
        });

        return true;
    }
}
