<?php

namespace App\Events\Partners\Webflow;

use Illuminate\Foundation\Events\Dispatchable;

class CollectionSchemaOutdated
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
