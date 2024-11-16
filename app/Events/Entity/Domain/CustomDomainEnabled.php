<?php

namespace App\Events\Entity\Domain;

use Illuminate\Foundation\Events\Dispatchable;

class CustomDomainEnabled
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
