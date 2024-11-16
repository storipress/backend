<?php

declare(strict_types=1);

namespace App\Events\Partners\Webflow;

use Illuminate\Foundation\Events\Dispatchable;

class Onboarded
{
    use Dispatchable;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public string $tenantId,
    ) {
        //
    }
}
