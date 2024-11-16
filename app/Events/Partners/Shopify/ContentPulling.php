<?php

namespace App\Events\Partners\Shopify;

use Illuminate\Foundation\Events\Dispatchable;

class ContentPulling
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
