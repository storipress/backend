<?php

namespace App\Policies;

use App\Models\User;

class BillingPolicy
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
