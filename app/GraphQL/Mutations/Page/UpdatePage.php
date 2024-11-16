<?php

namespace App\GraphQL\Mutations\Page;

use App\Events\Entity\Page\PageUpdated;
use App\Exceptions\NotFoundHttpException;
use App\GraphQL\Mutations\Mutation;
use App\Models\Tenant;
use App\Models\Tenants\Page;
use App\Models\Tenants\UserActivity;
use Illuminate\Support\Arr;
use Webmozart\Assert\Assert;

final class UpdatePage extends Mutation
{
    /**
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args): Page
    {
        $tenant = tenant();

        Assert::isInstanceOf($tenant, Tenant::class);

        $this->authorize('write', Page::class);

        $page = Page::find($args['id']);

        if (!($page instanceof Page)) {
            throw new NotFoundHttpException();
        }

        $attributes = Arr::except($args, ['id']);

        $origin = $page->only(array_keys($attributes));

        $page->update($attributes);

        PageUpdated::dispatch($tenant->id, $page->id, array_keys($attributes));

        UserActivity::log(
            name: 'page.update',
            subject: $page,
            data: [
                'old' => $origin,
                'new' => $attributes,
            ],
        );

        return $page;
    }
}
