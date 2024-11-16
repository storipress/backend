<?php

namespace App\GraphQL\Mutations\Site;

use App\GraphQL\Mutations\Mutation;
use App\Models\Tenant;

final class EnableCustomDomain extends Mutation
{
    /**
     * @param  array<string, string>  $args
     */
    public function __invoke($_, array $args): Tenant
    {
        return tenant_or_fail();
    }
}
