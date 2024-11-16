<?php

namespace App\GraphQL\Mutations\Page;

use App\Events\Entity\Page\PageDeleted;
use App\Exceptions\NotFoundHttpException;
use App\GraphQL\Mutations\Mutation;
use App\Models\Tenant;
use App\Models\Tenants\Page;
use App\Models\Tenants\UserActivity;
use Webmozart\Assert\Assert;

final class DeletePage extends Mutation
{
    /**
     * @param  array{
     *     id: string,
     * }  $args
     */
    public function __invoke($_, array $args): Page
    {
        $tenant = tenant();

        Assert::isInstanceOf($tenant, Tenant::class);

        $this->authorize('write', Page::class);

        $page = Page::find($args['id']);

        if (! ($page instanceof Page)) {
            throw new NotFoundHttpException();
        }

        $page->delete();

        PageDeleted::dispatch($tenant->id, $page->id);

        UserActivity::log(
            name: 'page.delete',
            subject: $page,
        );

        return $page;
    }
}
