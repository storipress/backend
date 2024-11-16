<?php

namespace App\Events\Entity\Desk;

use App\Events\Traits\HasAuthId;
use Illuminate\Foundation\Events\Dispatchable;

class DeskUpdated
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
        public int $deskId,
        public array $changes,
        public ?int $authId = null,
    ) {
        $this->setAuthIdIfRequired();
    }
}
