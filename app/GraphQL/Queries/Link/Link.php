<?php

namespace App\GraphQL\Queries\Link;

use App\Exceptions\NotFoundHttpException;
use App\Models\Link as LinkEntity;
use App\Models\Tenant;
use App\Models\User;

final class Link
{
    /**
     * @param  array{
     *     id: string,
     * }  $args
     */
    public function __invoke($_, array $args): ?LinkEntity
    {
        $tenant = tenant();

        if (!($tenant instanceof Tenant)) {
            throw new NotFoundHttpException();
        }

        $user = auth()->user();

        if (!($user instanceof User)) {
            throw new NotFoundHttpException();
        }

        return LinkEntity::where('tenant_id', '=', $tenant->id)
            ->find($args['id']);
    }
}
