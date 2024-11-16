<?php

namespace App\GraphQL\Mutations\Desk;

use App\Events\Entity\Desk\DeskUserRemoved;
use App\Exceptions\AccessDeniedHttpException;
use App\Exceptions\NotFoundHttpException;
use App\GraphQL\Mutations\Mutation;
use App\Models\Tenant;
use App\Models\Tenants\Desk;
use App\Models\Tenants\User;
use App\Models\Tenants\UserActivity;
use Segment\Segment;
use Webmozart\Assert\Assert;

final class RevokeUserFromDesk extends Mutation
{
    /**
     * @param  array<string, string>  $args
     */
    public function __invoke($_, array $args): User
    {
        $tenant = tenant();

        Assert::isInstanceOf($tenant, Tenant::class);

        $this->authorize('write', Desk::class);

        $user = User::find($args['user_id']);

        $desk = Desk::find($args['desk_id']);

        if (is_null($user) || is_null($desk)) {
            throw new NotFoundHttpException();
        }

        /** @var User $manipulator */
        $manipulator = User::find(auth()->user()?->getAuthIdentifier());

        if (!$manipulator->isAdmin() && !$manipulator->isInDesk($desk)) {
            throw new AccessDeniedHttpException();
        }

        if ($user->role === $manipulator->role || $user->isLevelHigherThan($manipulator)) {
            throw new AccessDeniedHttpException();
        }

        if ($user->isAdmin()) {
            return $user;
        }

        if (!$user->isInDesk($desk)) {
            return $user;
        }

        $user->desks()->detach($desk);

        $user->refresh();

        DeskUserRemoved::dispatch(
            $tenant->id,
            $desk->id,
            $user->id,
        );

        UserActivity::log(
            name: 'desk.users.remove',
            subject: $desk,
            data: [
                'user' => $user->id,
            ],
        );

        Segment::track([
            'userId' => (string) $manipulator->id,
            'event' => 'tenant_desk_user_removed',
            'properties' => [
                'tenant_uid' => $tenant->id,
                'tenant_name' => $tenant->name,
                'tenant_desk_uid' => (string) $desk->id,
                'tenant_user_uid' => (string) $user->id,
            ],
            'context' => [
                'groupId' => $tenant->id,
            ],
        ]);

        return $user;
    }
}
