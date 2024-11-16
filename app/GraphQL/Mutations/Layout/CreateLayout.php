<?php

namespace App\GraphQL\Mutations\Layout;

use App\Events\Entity\Layout\LayoutCreated;
use App\GraphQL\Mutations\Mutation;
use App\Models\Tenant;
use App\Models\Tenants\Layout;
use App\Models\Tenants\UserActivity;
use Webmozart\Assert\Assert;

final class CreateLayout extends Mutation
{
    /**
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args): Layout
    {
        $tenant = tenant();

        Assert::isInstanceOf($tenant, Tenant::class);

        $this->authorize('write', Layout::class);

        $layout = Layout::create($args);

        LayoutCreated::dispatch($tenant->id, $layout->id);

        UserActivity::log(
            name: 'layout.create',
            subject: $layout,
        );

        return $layout;
    }
}
