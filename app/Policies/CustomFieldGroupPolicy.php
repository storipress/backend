<?php

namespace App\Policies;

use App\Models\Tenant;
use App\Models\Tenants\User as TenantUser;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

class CustomFieldGroupPolicy
{
    public function read(Authenticatable $authenticatable): bool
    {
        if (!($authenticatable instanceof User || $authenticatable instanceof Tenant)) {
            return false;
        }

        return true;
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
            ->value('role');

        return in_array($role, ['owner', 'admin', 'editor'], true);
    }
}
