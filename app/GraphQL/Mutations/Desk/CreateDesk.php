<?php

namespace App\GraphQL\Mutations\Desk;

use App\Events\Entity\Desk\DeskCreated;
use App\GraphQL\Mutations\Mutation;
use App\Models\Tenant;
use App\Models\Tenants\Desk;
use App\Models\Tenants\UserActivity;
use Webmozart\Assert\Assert;

final class CreateDesk extends Mutation
{
    /**
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args): Desk
    {
        $tenant = tenant();

        Assert::isInstanceOf($tenant, Tenant::class);

        $this->authorize('write', Desk::class);

        $desk = Desk::create($args)->refresh();

        DeskCreated::dispatch($tenant->id, $desk->id);

        UserActivity::log(
            name: 'desk.create',
            subject: $desk,
        );

        return $desk;
    }
}
