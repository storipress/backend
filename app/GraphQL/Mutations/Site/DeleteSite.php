<?php

namespace App\GraphQL\Mutations\Site;

use App\Events\Entity\Tenant\TenantDeleted;
use App\Models\Tenant;
use App\Models\Tenants\UserActivity;
use Illuminate\Support\Facades\Hash;

class DeleteSite
{
    /**
     * @param  array{password: string}  $args
     */
    public function __invoke($_, array $args): bool
    {
        $tenant = tenant();

        if (! ($tenant instanceof Tenant)) {
            return false;
        }

        if ($tenant->owner->id !== auth()->id()) {
            return false;
        }

        if (! Hash::check($args['password'], $tenant->owner->password)) {
            return false;
        }

        TenantDeleted::dispatch($tenant->id);

        UserActivity::log(
            name: 'publication.delete',
        );

        return (bool) $tenant->delete();
    }
}
