<?php

namespace App\Events\Entity\Domain;

use Illuminate\Foundation\Events\Dispatchable;

class CustomDomainCheckRequested
{
    use Dispatchable;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public string $tenantId,
    ) {
        //
    }
}
