<?php

namespace App\Exceptions\Billing;

class InvalidQuantityException extends BillingException
{
    /**
     * Construct the exception.
     */
    public function __construct()
    {
        parent::__construct(
            422,
            'billing.invalid-quantity',
        );
    }
}
