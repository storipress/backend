<?php

declare(strict_types=1);

namespace App\Events\Partners\WordPress;

use App\Events\Traits\HasAuthId;
use Illuminate\Foundation\Events\Dispatchable;

class Disconnected
{
    use Dispatchable;
    use HasAuthId;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public string $tenantId,
        public ?int $authId = null,
    ) {
        $this->setAuthIdIfRequired();
    }
}
