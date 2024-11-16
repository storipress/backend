<?php

namespace App\Resources\Partners\Shopify;

class Shop
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public string $domain,
        public string $myshopifyDomain,
    ) {
        //
    }
}
