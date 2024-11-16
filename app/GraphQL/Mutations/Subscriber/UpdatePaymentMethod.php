<?php

namespace App\GraphQL\Mutations\Subscriber;

use App\Models\Subscriber;
use Stripe\Exception\ApiErrorException;
use Webmozart\Assert\Assert;

class UpdatePaymentMethod
{
    use StripeTrait;

    /**
     * @param  array<string, string>  $args
     *
     * @throws ApiErrorException
     */
    public function __invoke($_, array $args): bool
    {
        /** @var Subscriber $subscriber */
        $subscriber = auth()->user();

        Assert::isInstanceOf($subscriber, Subscriber::class);

        $payment = $subscriber->updateDefaultPaymentMethod($args['pm_id']);

        $this->syncPaymentMethodToTenants($subscriber, $payment);

        return true;
    }
}
