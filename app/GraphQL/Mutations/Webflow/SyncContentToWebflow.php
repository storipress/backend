<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\Webflow;

use App\Exceptions\BadRequestHttpException;
use App\Models\Tenants\Integration;
use App\Models\Tenants\UserActivity;

final readonly class SyncContentToWebflow
{
    /**
     * @param  array{}  $args
     */
    public function __invoke($_, array $args): bool
    {
        $tenant = tenant_or_fail();

        $exists = Integration::where('key', '=', 'webflow')
            ->activated()
            ->exists();

        if (!$exists) {
            throw new BadRequestHttpException();
        }

        // trigger content sync

        UserActivity::log(
            name: 'sync.content',
            data: [
                'key' => 'webflow',
            ],
        );

        return true;
    }
}
