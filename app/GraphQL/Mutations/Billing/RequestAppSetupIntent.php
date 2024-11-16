<?php

namespace App\GraphQL\Mutations\Billing;

use App\Exceptions\ErrorCode;
use App\Exceptions\HttpException;
use App\Models\User;
use App\Models\UserActivity;
use Webmozart\Assert\Assert;

class RequestAppSetupIntent extends BillingMutation
{
    /**
     * @param  array{
     *     payment?: string
     * }  $args
     *
     * @link https://stripe.com/docs/payments/setup-intents
     * @link https://laravel.com/docs/billing#payment-methods-for-subscriptions
     */
    public function __invoke($_, array $args): string
    {
        $user = auth()->user();

        if (! ($user instanceof User)) {
            throw new HttpException(ErrorCode::PERMISSION_FORBIDDEN);
        }

        $customer = $user->createOrGetStripeCustomer([
            'metadata' => [
                'id' => $user->getKey(),
                'type' => 'user',
            ],
        ]);

        $options = [
            'customer' => $customer->id,
        ];

        if (! empty($args['payment'])) {
            $options['payment_method'] = $args['payment'];
        }

        $intent = $user->createSetupIntent($options);

        Assert::stringNotEmpty($intent->client_secret);

        UserActivity::log(
            name: 'billing.payment.init',
        );

        return $intent->client_secret;
    }
}
