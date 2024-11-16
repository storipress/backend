<?php

namespace App\Policies;

use App\Enums\User\Status;
use App\Models\Tenants\User as TenantUser;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

class CustomDomainPolicy
{
    public function read(Authenticatable $user): bool
    {
        return false;
    }

    public function write(Authenticatable $authenticatable): bool
    {
        if (!($authenticatable instanceof User)) {
            return false;
        }

        if (!isset($authenticatable->access_token)) {
            return false;
        }

        $role = TenantUser::withoutEagerLoads()
            ->where('id', '=', $authenticatable->id)
            ->where('status', '=', Status::active())
            ->value('role');

        return in_array($role, ['owner', 'admin'], true);
    }
}
