<?php

namespace App\GraphQL\Mutations\Subscriber;

use App\Exceptions\AccessDeniedHttpException;
use App\Models\Subscriber;
use Webmozart\Assert\Assert;

class RequestSetupIntent
{
    /**
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args): string
    {
        $subscriber = auth()->user();

        if (!($subscriber instanceof Subscriber)) {
            throw new AccessDeniedHttpException();
        }

        if (filter_var($subscriber->email, FILTER_VALIDATE_EMAIL) === false) {
            throw new AccessDeniedHttpException();
        }

        $customer = $subscriber->createOrGetStripeCustomer([
            'metadata' => [
                'id' => $subscriber->getKey(),
                'type' => 'subscriber',
            ],
        ]);

        $intent = $subscriber->createSetupIntent([
            'customer' => $customer->id,
        ]);

        Assert::stringNotEmpty(
            $intent->client_secret,
            sprintf('Something went wrong when starting a setup intent, %d.', $subscriber->id),
        );

        return $intent->client_secret;
    }
}
