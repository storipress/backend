<?php

namespace App\GraphQL\Queries;

use App\Exceptions\NotFoundHttpException;
use App\Models\Tenant;

final class Site
{
    public function __invoke(): Tenant
    {
        /** @var Tenant|null $tenant */
        $tenant = tenant();

        if ($tenant === null) {
            throw new NotFoundHttpException();
        }

        return $tenant;
    }
}
