<?php

namespace App\Events\Entity\Integration;

use Illuminate\Foundation\Events\Dispatchable;

class IntegrationConfigurationUpdated
{
    use Dispatchable;

    /**
     * Create a new event instance.
     *
     * @param  array<string, mixed>  $changes
     * @param  array<string, mixed>  $original
     */
    public function __construct(
        public string $tenantId,
        public string $integrationKey,
        public array $changes,
        public array $original,
    ) {
        //
    }
}
