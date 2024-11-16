<?php

namespace App\Events\Entity\Layout;

use App\Events\Traits\HasAuthId;
use Illuminate\Foundation\Events\Dispatchable;

class LayoutUpdated
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
        public int $layoutId,
        public array $changes,
        public ?int $authId = null,
    ) {
        $this->setAuthIdIfRequired();
    }
}
