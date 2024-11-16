<?php

namespace App\Events\Entity\Tag;

use App\Events\Traits\HasAuthId;
use Illuminate\Foundation\Events\Dispatchable;

class TagCreated
{
    use Dispatchable;
    use HasAuthId;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public string $tenantId,
        public int $tagId,
        public ?int $authId = null,
    ) {
        $this->setAuthIdIfRequired();
    }
}
