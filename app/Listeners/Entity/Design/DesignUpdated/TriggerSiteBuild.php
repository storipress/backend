<?php

namespace App\Listeners\Entity\Design\DesignUpdated;

use App\Events\Entity\Design\DesignUpdated;
use App\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class TriggerSiteBuild implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(DesignUpdated $event): void
    {
        $tenant = Tenant::initialized()->find($event->tenantId);

        if (! ($tenant instanceof Tenant)) {
            return;
        }

        if (empty(array_intersect($event->changes, ['current', 'seo']))) {
            return;
        }

        $tenant->run(function () use ($event) {
            build_site('design:update', [
                'id' => $event->designKey,
            ]);
        });
    }
}
