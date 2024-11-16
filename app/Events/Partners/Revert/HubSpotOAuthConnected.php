<?php

namespace App\Events\Partners\Revert;

use Illuminate\Foundation\Events\Dispatchable;

class HubSpotOAuthConnected
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
