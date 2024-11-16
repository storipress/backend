<?php

namespace App\Events\Entity\Tag;

use App\Events\Traits\HasAuthId;
use Illuminate\Foundation\Events\Dispatchable;

class TagUpdated
{
    use Dispatchable;
    use HasAuthId;

    /**
     * Create a new event instance.
     *
     * @param  array<int, string>  $changes
     */
    public function __construct(
        public string $tenantId,
        public int $tagId,
        public array $changes,
        public ?int $authId = null,
    ) {
        $this->setAuthIdIfRequired();
    }
}
