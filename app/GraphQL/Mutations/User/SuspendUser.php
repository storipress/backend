<?php

namespace App\GraphQL\Mutations\User;

use App\Enums\User\Status;
use App\Exceptions\AccessDeniedHttpException;
use App\GraphQL\Mutations\Mutation;
use App\Models\Tenants\User;
use App\Models\Tenants\UserActivity;

final class SuspendUser extends Mutation
{
    /**
     * @param  array<string, string[]>  $args
     * @return User[]
     */
    public function __invoke($_, array $args): array
    {
        $this->authorize('write', User::class);

        /** @var User $manipulator */
        $manipulator = User::find(auth()->user()?->getAuthIdentifier());

        if (! in_array($manipulator->role, ['owner', 'admin'], true)) {
            throw new AccessDeniedHttpException();
        }

        $result = [];

        foreach ($args['ids'] as $id) {
            $target = User::find($id);

            if (is_null($target)) {
                continue;
            }

            if ($manipulator->getKey() === $target->getKey()) {
                continue;
            }

            if (! $manipulator->isLevelHigherThan($target)) {
                continue;
            }

            $target->update(['status' => Status::suspended()]);

            UserActivity::log(
                name: 'team.suspend',
                subject: $target,
            );

            // @todo missing update pivot table status field

            $result[] = $target;
        }

        return $result;
    }
}
