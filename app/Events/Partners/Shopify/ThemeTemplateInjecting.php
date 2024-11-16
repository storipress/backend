<?php

namespace App\Events\Partners\Shopify;

use Illuminate\Foundation\Events\Dispatchable;

class ThemeTemplateInjecting
{
    use Dispatchable;

    public function __construct(
        public string $tenantId,
    ) {
        //
    }
}
