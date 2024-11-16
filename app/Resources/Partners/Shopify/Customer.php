<?php

namespace App\Resources\Partners\Shopify;

class Customer
{
    public function __construct(
        public int $id,
        public string $email,
        public ?string $firstName,
        public ?string $lastName,
        public bool $acceptsMarketing,
        public bool $verifiedEmail,
    ) {
        //
    }
}
