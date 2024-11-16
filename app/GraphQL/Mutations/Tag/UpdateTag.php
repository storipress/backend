<?php

namespace App\GraphQL\Mutations\Tag;

use App\Events\Entity\Tag\TagUpdated;
use App\Exceptions\InternalServerErrorHttpException;
use App\Exceptions\NotFoundHttpException;
use App\GraphQL\Mutations\Mutation;
use App\Models\Tenants\Tag;
use App\Models\Tenants\UserActivity;
use Illuminate\Support\Arr;

class UpdateTag extends Mutation
{
    /**
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args): Tag
    {
        $tenant = tenant_or_fail();

        $tag = Tag::find($args['id']);

        if (!($tag instanceof Tag)) {
            throw new NotFoundHttpException();
        }

        $attributes = Arr::except($args, ['id']);

        $origin = $tag->only(array_keys($attributes));

        $updated = $tag->update($attributes);

        if (!$updated) {
            throw new InternalServerErrorHttpException();
        }

        TagUpdated::dispatch(
            $tenant->id,
            $tag->id,
            array_keys($attributes),
        );

        UserActivity::log(
            name: 'tag.update',
            subject: $tag,
            data: [
                'old' => $origin,
                'new' => $attributes,
            ],
        );

        return $tag;
    }
}
