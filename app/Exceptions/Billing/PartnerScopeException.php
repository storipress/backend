<?php

namespace App\Exceptions\Billing;

class PartnerScopeException extends BillingException
{
    /**
     * Construct the exception.
     */
    public function __construct()
    {
        parent::__construct(
            400,
            'billing.partner-scope',
        );
    }
}
