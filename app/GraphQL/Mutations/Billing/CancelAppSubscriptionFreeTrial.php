<?php

namespace App\GraphQL\Mutations\Billing;

class CancelAppSubscriptionFreeTrial extends BillingMutation
{
    /**
     * @param  array{}  $args
     */
    public function __invoke($_, array $args): bool
    {
        return false;
    }
}
