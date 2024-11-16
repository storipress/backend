<?php

namespace App\Events\Auth;

use Illuminate\Foundation\Events\Dispatchable;

class SignedIn
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
