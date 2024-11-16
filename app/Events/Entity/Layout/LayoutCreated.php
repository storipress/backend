<?php

namespace App\Events\Entity\Layout;

use App\Events\Traits\HasAuthId;
use Illuminate\Foundation\Events\Dispatchable;

class LayoutCreated
{
    use Dispatchable;
    use HasAuthId;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public string $tenantId,
        public int $layoutId,
        public ?int $authId = null,
    ) {
        $this->setAuthIdIfRequired();
    }
}
