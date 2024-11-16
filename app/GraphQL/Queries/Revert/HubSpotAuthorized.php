<?php

declare(strict_types=1);

namespace App\GraphQL\Queries\Revert;

use App\Models\Tenant;
use App\Models\Tenants\Integration;
use Storipress\Revert\Exceptions\RevertException;

final readonly class HubSpotAuthorized
{
    /**
     * @param  array{}  $args
     */
    public function __invoke(null $_, array $args): bool
    {
        $tenant = tenant();

        if (!($tenant instanceof Tenant)) {
            return false;
        }

        $token = config('services.revert.token');

        if (!is_not_empty_string($token)) {
            return false;
        }

        $integration = Integration::where('key', '=', 'hubspot')->first();

        if (!($integration instanceof Integration)) {
            return false;
        }

        if ($integration->activated_at) {
            return true;
        }

        try {
            $revert = app('revert')
                ->setToken($token)
                ->setCustomerId(sprintf('%s-hubspot', $tenant->id))
                ->connection()
                ->get();
        } catch (RevertException) {
            return false;
        }

        return $integration->update([
            'internals' => [
                't_id' => $revert->t_id,
                'tp_id' => $revert->tp_id,
                'tp_customer_id' => $revert->tp_customer_id,
                'tp_access_token' => $revert->tp_access_token,
                'tp_refresh_token' => $revert->tp_refresh_token,
                'app_config' => $revert->app_config,
            ],
            'activated_at' => now(),
        ]);
    }
}
