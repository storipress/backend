<?php

namespace App\Events\Entity\User;

use Illuminate\Foundation\Events\Dispatchable;

class UserUpdated
{
    use Dispatchable;

    /**
     * Create a new event instance.
     *
     * @param  array<string, mixed>  $changes
     */
    public function __construct(
        public int $userId,
        public array $changes,
    ) {
        //
    }
}
