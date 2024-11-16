<?php

namespace App\GraphQL\Mutations\User;

use App\Exceptions\NotFoundHttpException;
use App\GraphQL\Mutations\Mutation;
use App\Models\Tenant;
use App\Models\Tenants\User;
use App\Models\Tenants\UserActivity;
use App\Models\UserStatus;

class ChangeUserRoleForTesting extends Mutation
{
    /**
     * @param  array{
     *     id: string,
     *     role_id: string,
     * }  $args
     */
    public function __invoke($_, array $args): User
    {
        $tenant = tenant();

        if (!($tenant instanceof Tenant)) {
            throw new NotFoundHttpException();
        }

        if (!app()->environment(['local', 'testing', 'development'])) {
            throw new NotFoundHttpException();
        }

        $target = User::withoutEagerLoads()->find($args['id']);

        if (!($target instanceof User)) {
            throw new NotFoundHttpException();
        }

        $role = find_role($args['role_id']);

        $origin = $target->role;

        $target->update(['role' => $role->name]);

        UserStatus::withoutEagerLoads()
            ->where('tenant_id', '=', $tenant->id)
            ->where('user_id', '=', $target->id)
            ->update(['role' => $role->name]);

        UserActivity::log(
            name: 'team.role.change',
            subject: $target,
            data: [
                'source' => 'testing',
                'old' => $origin,
                'new' => $role->name,
            ],
            userId: $tenant->user_id,
        );

        return $target;
    }
}
