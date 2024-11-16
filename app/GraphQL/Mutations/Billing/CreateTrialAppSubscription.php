<?php

namespace App\GraphQL\Mutations\Billing;

use App\Exceptions\Billing\CustomerNotExistsException;
use App\Exceptions\Billing\InvalidBillingAddressException;
use App\Exceptions\Billing\PaymentNotSetException;
use App\Exceptions\Billing\SubscriptionExistsException;
use App\Exceptions\ErrorCode;
use App\Exceptions\HttpException;
use App\Exceptions\InternalServerErrorHttpException;
use App\Models\User;
use App\Models\UserActivity;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Laravel\Cashier\Exceptions\IncompletePayment;
use Segment\Segment;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\CardException;
use Stripe\Exception\InvalidRequestException;

use function Sentry\captureException;

final class CreateTrialAppSubscription extends BillingMutation
{
    /**
     * @param  array{}  $args
     *
     * @throws ApiErrorException
     * @throws Exception
     */
    public function __invoke($_, array $args): bool
    {
        $user = auth()->user();

        if (!($user instanceof User)) {
            throw new HttpException(ErrorCode::PERMISSION_FORBIDDEN);
        }

        if (!$user->hasStripeId()) {
            throw new CustomerNotExistsException();
        }

        if ($user->subscribed()) {
            throw new SubscriptionExistsException();
        }

        if ($user->subscriptions()->exists()) {
            throw new SubscriptionExistsException();
        }

        $customer = $user->asStripeCustomer(['subscriptions', 'sources']);

        if ((!$customer->sources || $customer->sources->isEmpty()) && $user->paymentMethods()->isEmpty()) {
            throw new PaymentNotSetException();
        }

        if ($customer->subscriptions && !$customer->subscriptions->isEmpty()) {
            throw new SubscriptionExistsException();
        }

        $trial = 'publisher-1-trial';

        $price = Arr::first(
            $this->priceIds(),
            fn (string $key) => Str::startsWith($key, 'publisher-') && Str::endsWith($key, '-monthly'),
        );

        if (!is_not_empty_string($price)) {
            throw new InternalServerErrorHttpException();
        }

        try {
            $subscription = $user->newSubscription('default')
                ->price($trial, 99)
                ->errorIfPaymentFails() // https://stripe.com/docs/api/subscriptions/create#create_subscription-payment_behavior
                ->create();
        } catch (CardException|IncompletePayment) {
            return false;
        } catch (InvalidRequestException $e) {
            if (Str::contains($e->getMessage(), 'location isn\'t recognized')) {
                throw new InvalidBillingAddressException();
            }

            captureException($e);

            return false;
        }

        if (!$subscription->active()) {
            return false;
        }

        $schedules = $user->stripe()->subscriptionSchedules;

        $subscriptionId = $subscription->asStripeSubscription()->id;

        $schedule = $schedules->create([
            'from_subscription' => $subscriptionId,
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

        UserActivity::log(
            name: 'billing.subscription.trial',
            data: [
                'subscription_id' => $subscriptionId,
                'schedule_id' => $schedule->id,
            ],
        );

        Segment::track([
            'userId' => (string) $user->id,
            'event' => 'user_trial_subscription_created',
            'properties' => [
                'type' => 'stripe',
                'partner_id' => $subscriptionId,
                'plan_id' => $trial,
                'schedule_id' => $schedule->id,
            ],
        ]);

        return $schedule->status === 'active';
    }
}
