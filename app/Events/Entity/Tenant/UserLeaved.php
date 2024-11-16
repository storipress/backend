<?php

namespace App\Events\Entity\Tenant;

use Illuminate\Foundation\Events\Dispatchable;

class UserLeaved
{
    use Dispatchable;

    /**
     * Create a new event instance.
     *
     * @param  array{
     *     webflow_id: string|null,
     *     wordpress_id: int|null,
     *     slug: string|null,
     * }  $data
     */
    public function __construct(
        public string $tenantId,
        public int $userId,
        public array $data,
    ) {
        //
    }
}
