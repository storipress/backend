<?php

namespace App\GraphQL\Queries;

use App\Exceptions\AccessDeniedHttpException;
use App\Exceptions\NotFoundHttpException;
use App\Models\Tenant;
use App\Models\Tenants\User as TenantUser;
use App\Models\User;

final class Me
{
    /**
     * @return User|TenantUser
     */
    public function __invoke()
    {
        /** @var User $authed */
        $authed = auth()->user();

        /** @var Tenant|null $tenant */
        $tenant = tenant();

        // central route
        if ($tenant === null) {
            return $authed;
        }

        // initialized has not finished
        if (! $tenant->initialized) {
            throw new NotFoundHttpException();
        }

        /** @var TenantUser|null $user */
        $user = TenantUser::find($authed->getKey());

        if ($user === null) {
            throw new AccessDeniedHttpException();
        }

        if ($user->suspended) {
            throw new AccessDeniedHttpException();
        }

        $attributes = [
            'email',
            'verified',
            'first_name',
            'last_name',
            'slug',
            'gender',
            'birthday',
            'phone_number',
            'location',
            'bio',
            'website',
            'socials',
            'avatar',
        ];

        foreach ($attributes as $key) {
            $user->setAttribute($key, $authed->getAttribute($key));
        }

        return $user;
    }
}
