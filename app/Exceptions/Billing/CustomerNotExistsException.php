<?php

namespace App\Exceptions\Billing;

class CustomerNotExistsException extends BillingException
{
    /**
     * Construct the exception.
     */
    public function __construct()
    {
        parent::__construct(
            400,
            'billing.customer-not-found',
        );
    }
}
