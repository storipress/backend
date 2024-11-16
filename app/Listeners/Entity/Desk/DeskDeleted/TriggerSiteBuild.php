<?php

namespace App\Listeners\Entity\Desk\DeskDeleted;

use App\Events\Entity\Desk\DeskDeleted;
use App\Models\Tenant;
use App\Models\Tenants\Desk;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class TriggerSiteBuild implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(DeskDeleted $event): void
    {
        $tenant = Tenant::initialized()->find($event->tenantId);

        if (!($tenant instanceof Tenant)) {
            return;
        }

        if ($tenant->is_ssr) {
            return;
        }

        $tenant->run(function () use ($event) {
            $desk = Desk::onlyTrashed()->find($event->deskId);

            if (!($desk instanceof Desk)) {
                return;
            }

            build_site('desk:delete', [
                'id' => $event->deskId,
                'parent' => $desk->desk_id,
            ]);
        });
    }
}
