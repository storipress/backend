<?php

namespace App\Exceptions\Billing;

class InvalidBillingAddressException extends BillingException
{
    /**
     * Construct the exception.
     */
    public function __construct()
    {
        parent::__construct(
            400,
            'billing.invalid-address',
        );
    }
}
