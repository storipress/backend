<?php

namespace App\Exceptions\Billing;

class SubscriptionExistsException extends BillingException
{
    /**
     * Construct the exception.
     */
    public function __construct()
    {
        parent::__construct(
            409,
            'billing.subscription-exists',
        );
    }
}
