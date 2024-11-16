<?php

namespace App\GraphQL\Mutations\Link;

use App\Enums\Link\Source;
use App\Enums\Link\Target;
use App\Exceptions\ErrorCode;
use App\Exceptions\HttpException;
use App\Models\Link;
use App\Models\Tenant;
use App\Models\Tenants\UserActivity;
use App\Models\User;

class CreateLink
{
    /**
     * @param  array{
     *     source: Source,
     *     value?: string,
     *     target_type?: Target,
     *     target_id?: string,
     * }  $args
     */
    public function __invoke($_, array $args): Link
    {
        $tenant = tenant();

        if (!($tenant instanceof Tenant)) {
            throw new HttpException(ErrorCode::NOT_FOUND);
        }

        $user = auth()->user();

        if (!($user instanceof User)) {
            throw new HttpException(ErrorCode::PERMISSION_FORBIDDEN);
        }

        $link = Link::create([
            'tenant_id' => $tenant->id,
            'source' => $args['source'],
            'reference' => empty($args['value']),
            'value' => $args['value'] ?? null,
            'target_tenant' => $tenant->id,
            'target_type' => $args['target_type'] ?? null,
            'target_id' => $args['target_id'] ?? null,
        ]);

        UserActivity::log(
            name: 'link.create',
            subject: $link,
        );

        return $link;
    }
}
