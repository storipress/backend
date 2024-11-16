<?php

namespace App\GraphQL\Mutations\Site;

use App\Exceptions\BadRequestHttpException;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserActivity;

class HidePublication
{
    /**
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args): bool
    {
        /** @var User $user */
        $user = auth()->user();

        /** @var Tenant|null $tenant */
        $tenant = $user
            ->tenants()
            ->where('tenants.id', $args['id'])
            ->first(['tenants.id']);

        if (is_null($tenant)) {
            throw new BadRequestHttpException();
        }

        $updated = $tenant
            ->tenant_user_pivot
            ->update(['hidden' => true]);

        UserActivity::log(
            name: 'account.publications.hide',
            subject: $tenant,
        );

        return $updated;
    }
}
