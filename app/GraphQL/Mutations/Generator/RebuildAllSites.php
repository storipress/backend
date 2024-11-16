<?php

namespace App\GraphQL\Mutations\Generator;

use App\Builder\ReleaseEventsBuilder;
use App\GraphQL\Mutations\Mutation;

final class RebuildAllSites extends Mutation
{
    /**
     * @param  array<string, mixed>  $args
     *
     * @retrun bool
     */
    public function __invoke($_, array $args): bool
    {
        runForTenants(function () {
            (new ReleaseEventsBuilder())->handle('site:rebuild');
        });

        return true;
    }
}
