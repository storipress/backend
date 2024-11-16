<?php

namespace App\Exceptions\Billing;

class NoGracePeriodSubscriptionException extends BillingException
{
    /**
     * Construct the exception.
     */
    public function __construct()
    {
        parent::__construct(
            400,
            'billing.no-grace-period-subscription',
        );
    }
}
