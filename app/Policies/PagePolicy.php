<?php

namespace App\Policies;

use App\Models\User;

class PagePolicy
{
    public function read(User $user): bool
    {
        return true;
    }

    public function write(User $user): bool
    {
        return true;
    }
}
