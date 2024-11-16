<?php

namespace App\Listeners\Entity\Integration\IntegrationUpdated;

use App\Events\Entity\Article\AutoPostingPathUpdated;
use App\Events\Entity\Integration\IntegrationUpdated;
use App\Models\Tenant;
use App\Models\Tenants\ArticleAutoPosting;
use App\Models\Tenants\Integration;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class UpdateShopifyPrefix implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Determine whether the listener should be queued.
     */
    public function shouldQueue(IntegrationUpdated $event): bool
    {
        return $event->integrationKey === 'shopify';
    }

    /**
     * Handle the event.
     */
    public function handle(IntegrationUpdated $event): void
    {
        $tenant = Tenant::initialized()->find($event->tenantId);

        if (! ($tenant instanceof Tenant)) {
            return;
        }

        $tenant->run(function () use ($event) {
            $integration = Integration::find($event->integrationKey);

            if (! ($integration instanceof Integration)) {
                return;
            }

            $prefix = $integration->data['prefix'] ?? null;

            if (empty($prefix)) {
                return;
            }

            ArticleAutoPosting::where('platform', '=', $event->integrationKey)
                ->update([
                    'prefix' => $prefix,
                ]);

            AutoPostingPathUpdated::dispatch('shopify', $event->tenantId);
        });
    }
}
