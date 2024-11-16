<?php

namespace App\Exceptions\Billing;

class InvalidPaymentMethodIdException extends BillingException
{
    /**
     * Construct the exception.
     */
    public function __construct()
    {
        parent::__construct(
            422,
            'billing.invalid-payment-method-id',
        );
    }
}
