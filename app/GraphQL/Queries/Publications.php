<?php

namespace App\GraphQL\Queries;

use App\Exceptions\AccessDeniedHttpException;
use App\Models\Tenant;
use App\Models\User;
use Stancl\Tenancy\Database\TenantCollection;

final class Publications
{
    /**
     * @param  array<string, mixed>  $args
     * @return TenantCollection<int, Tenant>
     */
    public function __invoke($_, array $args): TenantCollection
    {
        $authed = auth()->user();

        if (!($authed instanceof User)) {
            throw new AccessDeniedHttpException();
        }

        // @phpstan-ignore-next-line
        return $authed->publications()
            ->withoutEagerLoads()
            ->where('initialized', '=', true)
            ->get();
    }
}
