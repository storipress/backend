<?php

namespace App\Exceptions\Billing;

class SubscriptionInGracePeriodException extends BillingException
{
    /**
     * Construct the exception.
     */
    public function __construct()
    {
        parent::__construct(
            400,
            'billing.subscription-in-grace-period',
        );
    }
}
