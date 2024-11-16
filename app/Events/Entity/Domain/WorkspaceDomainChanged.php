<?php

namespace App\Events\Entity\Domain;

use Illuminate\Foundation\Events\Dispatchable;

class WorkspaceDomainChanged
{
    use Dispatchable;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public string $tenantId,
        public string $origin,
    ) {
        //
    }
}
