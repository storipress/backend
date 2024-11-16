<?php

namespace App\Listeners\Entity\Integration\IntegrationUpdated;

use App\Events\Entity\Integration\IntegrationUpdated;
use App\Models\Tenant;
use App\Models\Tenants\ArticleAutoPosting;
use App\Models\Tenants\Integration;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class UpdateWebflowDomain implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Determine whether the listener should be queued.
     */
    public function shouldQueue(IntegrationUpdated $event): bool
    {
        return $event->integrationKey === 'webflow';
    }

    public function handle(IntegrationUpdated $event): void
    {
        $tenant = Tenant::initialized()->find($event->tenantId);

        if (!($tenant instanceof Tenant)) {
            return;
        }

        $tenant->run(function () use ($event) {
            $integration = Integration::find($event->integrationKey);

            if (!($integration instanceof Integration)) {
                return;
            }

            $domain = $integration->data['domain'] ?? null;

            if (empty($domain)) {
                return;
            }

            ArticleAutoPosting::where('platform', '=', $event->integrationKey)
                ->update([
                    'domain' => $domain,
                ]);
        });
    }
}
