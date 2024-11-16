<?php

namespace App\GraphQL\Mutations\Site;

use App\Enums\User\Status;
use App\Exceptions\BadRequestHttpException;
use App\Exceptions\NotFoundHttpException;
use App\Models\Tenant;
use App\Models\Tenants\User;
use App\Models\UserActivity;
use Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedById;

class LeavePublication
{
    /**
     * @param  array<string, string>  $args
     *
     * @throws TenantCouldNotBeIdentifiedById
     */
    public function __invoke($_, array $args): bool
    {
        $tenant = Tenant::find($args['id']);

        if ($tenant === null) {
            throw new NotFoundHttpException();
        }

        tenancy()->initialize($args['id']);

        /** @var User|null $user */
        $user = User::find(auth()->user()?->getAuthIdentifier());

        if ($user === null) {
            throw new BadRequestHttpException();
        }

        if ($user->role === 'owner') {
            throw new BadRequestHttpException();
        }

        /** @var Tenant $tenant */
        $tenant = tenant();

        /** @var \App\Models\User $base */
        $base = $tenant
            ->users()
            ->where('users.id', $user->getKey())
            ->first(['users.id']);

        $base->tenant_user_pivot
            ->update(['status' => Status::suspended()]);

        $user->update(['status' => Status::suspended()]);

        UserActivity::log(
            name: 'account.publications.leave',
            subject: $tenant,
        );

        return true;
    }
}
