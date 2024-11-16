<?php

namespace App\Events\Entity\Tenant;

use Illuminate\Foundation\Events\Dispatchable;

class UserRoleChanged
{
    use Dispatchable;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public string $tenantId,
        public int $userId,
        public string $origin,
    ) {
        //
    }
}
