<?php

namespace App\GraphQL\Mutations\Tag;

use App\Events\Entity\Tag\TagDeleted;
use App\Exceptions\AccessDeniedHttpException;
use App\Exceptions\NotFoundHttpException;
use App\GraphQL\Mutations\Mutation;
use App\Models\Tenants\Tag;
use App\Models\Tenants\User;
use App\Models\Tenants\UserActivity;

class DeleteTag extends Mutation
{
    /**
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args): Tag
    {
        $tenant = tenant_or_fail();

        $user = User::find(auth()->id());

        if ($user === null || !$user->isAdmin()) {
            throw new AccessDeniedHttpException();
        }

        $tag = Tag::find($args['id']);

        if (!($tag instanceof Tag)) {
            throw new NotFoundHttpException();
        }

        $tag->articles()->detach();

        $tag->delete();

        TagDeleted::dispatch($tenant->id, $tag->id);

        UserActivity::log(
            name: 'tag.delete',
            subject: $tag,
        );

        return $tag;
    }
}
