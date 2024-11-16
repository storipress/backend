<?php

namespace App\Events\Partners\Shopify;

use App\Resources\Partners\Shopify\Shop;
use Illuminate\Foundation\Events\Dispatchable;

class OAuthConnected
{
    use Dispatchable;

    /**
     * Create a new event instance.
     *
     * @param  string[]  $scopes
     * @return void
     */
    public function __construct(
        public string $token,
        public array $scopes,
        public Shop $shop,
        public string $tenantId,
    ) {
        //
    }
}
