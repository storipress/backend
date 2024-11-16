<?php

namespace App\Exceptions\Billing;

class PaymentIncompleteException extends BillingException
{
    /**
     * Construct the exception.
     */
    public function __construct()
    {
        parent::__construct(
            400,
            'billing.payment-incomplete',
        );
    }
}
