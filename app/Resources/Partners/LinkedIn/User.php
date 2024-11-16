<?php

namespace App\Resources\Partners\LinkedIn;

class User
{
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
        public ?string $avatar,
    ) {
        //
    }
}
