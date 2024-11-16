<?php

namespace App\GraphQL\Mutations\Integration;

use App\Events\Entity\Integration\IntegrationUpdated;
use App\Exceptions\ErrorCode;
use App\Exceptions\HttpException;
use App\Models\Tenant;
use App\Models\Tenants\Integration;
use App\Models\Tenants\UserActivity;
use Illuminate\Support\Str;
use Webmozart\Assert\Assert;

final class UpdateIntegration extends IntegrationMutation
{
    /**
     * @param  array{
     *     key: string,
     *     data: mixed,
     * }  $args
     */
    public function __invoke($_, array $args): Integration
    {
        $tenant = tenant();

        Assert::isInstanceOf($tenant, Tenant::class);

        $this->authorize('write', Integration::class);

        $key = $args['key'];

        if (!$this->validate($key)) {
            $renames = [
                'linkedin' => 'LinkedIn',
            ];

            $key = $renames[$key] ?? $key;

            throw new HttpException(ErrorCode::INTEGRATION_FORBIDDEN_REQUEST, ['key' => Str::studly($key)]);
        }

        $original = $this->getIntegration($key)->data;

        $integration = $this->update($key, [
            'data' => $args['data'],
        ]);

        IntegrationUpdated::dispatch($tenant->id, $key, ['data']);

        UserActivity::log(
            name: 'integration.update',
            data: [
                'key' => $integration->getKey(),
                'old' => $original,
                'new' => $args['data'],
            ],
        );

        return $integration;
    }
}
