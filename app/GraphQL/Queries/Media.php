<?php

namespace App\GraphQL\Queries;

use App\Exceptions\AccessDeniedHttpException;
use App\Exceptions\NotFoundHttpException;
use App\Models\Media as MediaModel;
use App\Models\Tenant;
use App\Models\User;

final class Media
{
    /**
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args): MediaModel
    {
        /** @var User|null $authed */
        $authed = auth()->user();

        if ($authed === null) {
            throw new AccessDeniedHttpException();
        }

        /** @var Tenant|null $tenant */
        $tenant = tenant();

        if ($tenant === null) {
            throw new NotFoundHttpException();
        }

        $tenantId = $tenant->getKey();

        $token = $args['key'];

        /** @var MediaModel|null $media */
        $media = tenancy()->central(function () use ($tenantId, $token) {
            return MediaModel::where('token', $token)
                ->where('tenant_id', $tenantId) // ensure this request is from the correct tenant.
                ->first();
        });

        if ($media === null) {
            throw new NotFoundHttpException();
        }

        return $media;
    }
}
