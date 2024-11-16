<?php

namespace App\Events\Entity\Block;

use App\Events\Traits\HasAuthId;
use Illuminate\Foundation\Events\Dispatchable;

class BlockDeleted
{
    use Dispatchable;
    use HasAuthId;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public string $tenantId,
        public int $blockId,
        public ?int $authId = null,
    ) {
        $this->setAuthIdIfRequired();
    }
}
