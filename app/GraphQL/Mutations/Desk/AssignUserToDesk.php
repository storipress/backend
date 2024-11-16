<?php

namespace App\GraphQL\Mutations\Desk;

use App\Events\Entity\Desk\DeskUserAdded;
use App\Exceptions\AccessDeniedHttpException;
use App\Exceptions\NotFoundHttpException;
use App\GraphQL\Mutations\Mutation;
use App\Models\Tenant;
use App\Models\Tenants\Desk;
use App\Models\Tenants\User;
use App\Models\Tenants\UserActivity;
use Segment\Segment;
use Webmozart\Assert\Assert;

final class AssignUserToDesk extends Mutation
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

        if ($user->isAdmin()) {
            return $user;
        }

        if ($user->isInDesk($desk)) {
            return $user;
        }

        $user->desks()->attach($desk);

        $user->refresh();

        DeskUserAdded::dispatch(
            $tenant->id,
            $desk->id,
            $user->id,
        );

        UserActivity::log(
            name: 'desk.users.add',
            subject: $desk,
            data: [
                'user' => $user->id,
            ],
        );

        Segment::track([
            'userId' => (string) $manipulator->id,
            'event' => 'tenant_desk_user_added',
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
