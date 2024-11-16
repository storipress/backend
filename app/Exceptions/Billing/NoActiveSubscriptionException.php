<?php

namespace App\Exceptions\Billing;

class NoActiveSubscriptionException extends BillingException
{
    /**
     * Construct the exception.
     */
    public function __construct()
    {
        parent::__construct(
            400,
            'billing.no-active-subscription',
        );
    }
}
