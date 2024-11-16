<?php

namespace App\Listeners\Entity\Integration\IntegrationActivated;

use App\Events\Entity\Integration\IntegrationActivated;
use App\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class TriggerSiteBuild implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(IntegrationActivated $event): void
    {
        $tenant = Tenant::initialized()->find($event->tenantId);

        if (!($tenant instanceof Tenant)) {
            return;
        }

        $tenant->run(function () use ($event) {
            build_site('integration:activate', [
                'id' => $event->integrationKey,
            ]);
        });
    }
}
