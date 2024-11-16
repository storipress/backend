<?php

namespace App\Events\Entity\Desk;

use App\Events\Traits\HasAuthId;
use Illuminate\Foundation\Events\Dispatchable;

class DeskDeleted
{
    use Dispatchable;
    use HasAuthId;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public string $tenantId,
        public int $deskId,
        public ?int $authId = null,
    ) {
        $this->setAuthIdIfRequired();
    }
}
