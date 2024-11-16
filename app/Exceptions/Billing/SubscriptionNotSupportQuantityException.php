<?php

namespace App\Exceptions\Billing;

class SubscriptionNotSupportQuantityException extends BillingException
{
    /**
     * Construct the exception.
     */
    public function __construct()
    {
        parent::__construct(
            400,
            'billing.subscription-not-support-quantity',
        );
    }
}
