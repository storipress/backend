<?php

namespace App\GraphQL\Mutations\Page;

use App\Events\Entity\Page\PageCreated;
use App\GraphQL\Mutations\Mutation;
use App\Models\Tenant;
use App\Models\Tenants\Page;
use App\Models\Tenants\UserActivity;
use Webmozart\Assert\Assert;

final class CreatePage extends Mutation
{
    /**
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args): Page
    {
        $tenant = tenant();

        Assert::isInstanceOf($tenant, Tenant::class);

        $this->authorize('write', Page::class);

        $page = Page::create($args)->refresh();

        PageCreated::dispatch($tenant->id, $page->id);

        UserActivity::log(
            name: 'page.create',
            subject: $page,
        );

        return $page;
    }
}
