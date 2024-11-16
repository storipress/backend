<?php

namespace App\Events\Entity\Page;

use App\Events\Traits\HasAuthId;
use Illuminate\Foundation\Events\Dispatchable;

class PageCreated
{
    use Dispatchable;
    use HasAuthId;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public string $tenantId,
        public int $pageId,
        public ?int $authId = null,
    ) {
        $this->setAuthIdIfRequired();
    }
}
