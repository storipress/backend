<?php

namespace App\GraphQL\Mutations\Billing;

use App\Exceptions\Billing\InvalidPaymentMethodIdException;
use App\Exceptions\ErrorCode;
use App\Exceptions\HttpException;
use App\Models\User;
use App\Models\UserActivity;
use Illuminate\Support\Str;
use Segment\Segment;
use Stripe\Exception\InvalidRequestException;

use function Sentry\captureException;

class UpdateAppPaymentMethod extends BillingMutation
{
    /**
     * @param  array{
     *     token: string,
     *     country?: string,
     *     postal_code?: string,
     * } $args
     *
     * @link https://laravel.com/docs/billing#updating-the-default-payment-method
     */
    public function __invoke($_, array $args): bool
    {
        $user = auth()->user();

        if (! ($user instanceof User)) {
            throw new HttpException(ErrorCode::PERMISSION_FORBIDDEN);
        }

        $id = $args['token'];

        try {
            $user->updateDefaultPaymentMethod($id);
        } catch (InvalidRequestException $e) {
            if (Str::contains($e->getMessage(), 'The customer does not have a payment method with the ID')) {
                throw new InvalidPaymentMethodIdException();
            }

            captureException($e);

            return false;
        }

        $stripe = $user->stripe()->paymentMethods;

        $payment = $stripe->retrieve($id);

        if (empty($user->name ?: '')) {
            $name = $payment->billing_details['name'];

            if (is_not_empty_string($name)) {
                $names = explode(' ', $name, 2);

                $user->update([
                    'first_name' => $names[0],
                    'last_name' => $names[1] ?? '',
                ]);
            }
        }

        if (! empty($args['country'])) {
            $stripe->update($id, [
                'billing_details' => [
                    'address' => [
                        'country' => $args['country'],
                        'postal_code' => $args['postal_code'] ?? null,
                    ],
                ],
            ]);
        }

        UserActivity::log(
            name: 'billing.payment.update',
        );

        Segment::track([
            'userId' => (string) $user->id,
            'event' => 'user_payment_method_updated',
            'properties' => [
                'type' => 'stripe',
                'payment_method_id' => $id,
            ],
        ]);

        return true;
    }
}
