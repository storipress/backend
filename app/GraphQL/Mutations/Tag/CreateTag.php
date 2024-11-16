<?php

namespace App\GraphQL\Mutations\Tag;

use App\Events\Entity\Tag\TagCreated;
use App\GraphQL\Mutations\Mutation;
use App\Models\Tenants\Tag;
use App\Models\Tenants\UserActivity;

class CreateTag extends Mutation
{
    /**
     * @param  array{
     *     name: string,
     *     description?: string,
     * }  $args
     */
    public function __invoke($_, array $args): Tag
    {
        $tenant = tenant_or_fail();

        $this->authorize('write', Tag::class);

        $tag = Tag::withTrashed()->updateOrCreate(
            ['name' => $args['name']],
            [
                'description' => $args['description'] ?? null,
                'deleted_at' => null,
            ],
        );

        TagCreated::dispatch($tenant->id, $tag->id);

        UserActivity::log(
            name: 'tag.create',
            subject: $tag,
        );

        return $tag->refresh();
    }
}
