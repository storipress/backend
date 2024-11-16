<?php

namespace App\Events\Entity\Account;

use Illuminate\Foundation\Events\Dispatchable;

class AvatarRemoved
{
    use Dispatchable;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public int $userId,
    ) {
        //
    }
}
