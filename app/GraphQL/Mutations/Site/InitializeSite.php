<?php

namespace App\GraphQL\Mutations\Site;

use App\Exceptions\InternalServerErrorHttpException;
use App\GraphQL\Mutations\Mutation;
use App\Jobs\InitializeSite as InitializeSiteJob;
use App\Models\Tenant;
use App\Models\Tenants\Desk;
use Illuminate\Support\Str;

final class InitializeSite extends Mutation
{
    /**
     * @param  array<string, string|array<int, string>>  $args
     */
    public function __invoke($_, array $args): Tenant
    {
        /** @var Tenant $tenant */
        $tenant = tenant();

        if ($tenant->initialized) {
            return $tenant;
        }

        // step 1: create desks
        foreach ((array) $args['desks'] as $name) {
            Desk::create(compact('name'));
        }

        /** @var string $name */
        $name = $args['publication'];

        // step 2: update site info
        $updated = $tenant->update([
            'name' => $name,
            'workspace' => mb_strtolower(Str::camel($name)),
            'initialized' => true,
        ]);

        if (!$updated) {
            throw new InternalServerErrorHttpException();
        }

        // step 3: use async job to handle domain setup
        InitializeSiteJob::dispatch([
            'id' => $tenant->id,
        ]);

        return $tenant;
    }
}
