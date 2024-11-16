<?php

namespace App\Events\Entity\Integration;

use Illuminate\Foundation\Events\Dispatchable;

class IntegrationDeactivated
{
    use Dispatchable;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public string $tenantId,
        public string $integrationKey,
    ) {
        //
    }
}
