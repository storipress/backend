<?php

namespace App\Events\Partners\Shopify;

use Illuminate\Foundation\Events\Dispatchable;

class ArticlesSynced
{
    use Dispatchable;

    public function __construct(
        public string $tenantId,
    ) {
        //
    }
}
