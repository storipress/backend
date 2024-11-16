<?php

namespace App\Events\Entity\Tenant;

use Illuminate\Foundation\Events\Dispatchable;

class TenantUpdated
{
    use Dispatchable;

    /**
     * Create a new event instance.
     *
     * @param  string[]  $changes
     */
    public function __construct(
        public string $tenantId,
        public array $changes = [],
    ) {
        //
    }
}
