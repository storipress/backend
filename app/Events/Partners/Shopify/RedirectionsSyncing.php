<?php

namespace App\Events\Partners\Shopify;

use Illuminate\Foundation\Events\Dispatchable;

class RedirectionsSyncing
{
    use Dispatchable;

    public function __construct(
        public string $tenantId,
    ) {
        //
    }
}
