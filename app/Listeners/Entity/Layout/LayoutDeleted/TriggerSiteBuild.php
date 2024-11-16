<?php

namespace App\Listeners\Entity\Layout\LayoutDeleted;

use App\Events\Entity\Layout\LayoutDeleted;
use App\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class TriggerSiteBuild implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(LayoutDeleted $event): void
    {
        $tenant = Tenant::initialized()->find($event->tenantId);

        if (! ($tenant instanceof Tenant)) {
            return;
        }

        $tenant->run(function () use ($event) {
            build_site('layout:delete', [
                'id' => $event->layoutId,
            ]);
        });
    }
}
