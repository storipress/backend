<?php

namespace App\Exceptions\Billing;

class NoOnTrialSubscriptionException extends BillingException
{
    /**
     * Construct the exception.
     */
    public function __construct()
    {
        parent::__construct(
            400,
            'billing.no-on-trial-subscription',
        );
    }
}
