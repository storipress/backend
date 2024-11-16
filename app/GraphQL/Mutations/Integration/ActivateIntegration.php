<?php

namespace App\GraphQL\Mutations\Integration;

use App\Events\Entity\Integration\IntegrationActivated;
use App\Models\Tenant;
use App\Models\Tenants\Integration;
use App\Models\Tenants\UserActivity;
use Webmozart\Assert\Assert;

final class ActivateIntegration extends IntegrationMutation
{
    /**
     * @param  array<string, string>  $args
     */
    public function __invoke($_, array $args): Integration
    {
        $tenant = tenant();

        Assert::isInstanceOf($tenant, Tenant::class);

        $this->authorize('write', Integration::class);

        $integration = $this->update($args['key'], [
            'activated_at' => now(),
        ]);

        IntegrationActivated::dispatch($tenant->id, $integration->key);

        UserActivity::log(
            name: 'integration.activate',
            data: [
                'key' => $integration->getKey(),
            ],
        );

        return $integration;
    }
}
