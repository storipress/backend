<?php

namespace App\Events\Entity\Block;

use App\Events\Traits\HasAuthId;
use Illuminate\Foundation\Events\Dispatchable;

class BlockUpdated
{
    use Dispatchable;
    use HasAuthId;

    /**
     * Create a new event instance.
     *
     * @param  array<string, mixed>  $changes
     */
    public function __construct(
        public string $tenantId,
        public int $blockId,
        public array $changes,
        public ?int $authId = null,
    ) {
        $this->setAuthIdIfRequired();
    }
}
