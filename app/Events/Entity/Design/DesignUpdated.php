<?php

namespace App\Events\Entity\Design;

use App\Events\Traits\HasAuthId;
use Illuminate\Foundation\Events\Dispatchable;

class DesignUpdated
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
        public string $designKey,
        public array $changes,
        public ?int $authId = null,
    ) {
        $this->setAuthIdIfRequired();
    }
}
