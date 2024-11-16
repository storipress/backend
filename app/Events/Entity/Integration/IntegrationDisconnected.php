<?php

namespace App\Events\Entity\Integration;

use Illuminate\Foundation\Events\Dispatchable;

class IntegrationDisconnected
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
