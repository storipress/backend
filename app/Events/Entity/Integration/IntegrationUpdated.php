<?php

namespace App\Events\Entity\Integration;

use Illuminate\Foundation\Events\Dispatchable;

class IntegrationUpdated
{
    use Dispatchable;

    /**
     * Create a new event instance.
     *
     * @param  array<int, string>  $changes
     */
    public function __construct(
        public string $tenantId,
        public string $integrationKey,
        public array $changes,
    ) {
        //
    }
}
