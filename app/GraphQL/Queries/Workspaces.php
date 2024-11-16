<?php

namespace App\GraphQL\Queries;

use App\Models\Tenant;
use App\Models\User;

final class Workspaces
{
    /**
     * @param  array<string, mixed>  $args
     * @return array<Tenant>
     */
    public function __invoke($_, array $args): array
    {
        $authed = auth()->user();

        if (! ($authed instanceof User)) {
            return [];
        }

        $tenants = [];

        foreach ($authed->tenants as $tenant) {
            if (! $tenant->initialized) {
                continue;
            }

            $tenant->setAttribute('role', $tenant->tenant_user_pivot->role);

            $tenant->setAttribute('status', $tenant->tenant_user_pivot->status);

            $tenant->setAttribute('hidden', $tenant->tenant_user_pivot->hidden);

            $tenants[] = $tenant;
        }

        return $tenants;
    }
}
