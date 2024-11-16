<?php

declare(strict_types=1);

namespace App\Events\Partners\Webflow;

use App\Events\Traits\HasAuthId;
use Illuminate\Foundation\Events\Dispatchable;

class CollectionConnected
{
    use Dispatchable;
    use HasAuthId;

    /**
     * Create a new event instance.
     *
     * @param  'blog'|'author'|'desk'|'tag'  $collectionKey
     */
    public function __construct(
        public string $tenantId,
        public string $collectionKey,
        public ?int $authId = null,
    ) {
        $this->setAuthIdIfRequired();
    }
}
