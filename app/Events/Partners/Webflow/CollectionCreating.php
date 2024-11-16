<?php

declare(strict_types=1);

namespace App\Events\Partners\Webflow;

use App\Enums\Webflow\CollectionType;
use App\Events\Traits\HasAuthId;
use Illuminate\Foundation\Events\Dispatchable;

class CollectionCreating
{
    use Dispatchable;
    use HasAuthId;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public string $tenantId,
        public CollectionType $collectionType,
        public ?int $authId = null,
    ) {
        $this->setAuthIdIfRequired();
    }
}
