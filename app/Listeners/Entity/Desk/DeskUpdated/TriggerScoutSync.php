<?php

namespace App\Listeners\Entity\Desk\DeskUpdated;

use App\Events\Entity\Desk\DeskUpdated;
use App\Models\Tenant;
use App\Models\Tenants\Desk;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class TriggerScoutSync implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(DeskUpdated $event): void
    {
        $tenant = Tenant::initialized()->find($event->tenantId);

        if (! ($tenant instanceof Tenant)) {
            return;
        }

        if (empty(array_intersect($event->changes, ['layout_id', 'name', 'slug']))) {
            return;
        }

        $tenant->run(function () use ($event) {
            $desk = Desk::find($event->deskId);

            if (! ($desk instanceof Desk)) {
                return;
            }

            $desk->articles()->chunkById(50, fn ($articles) => $articles->searchable());
        });
    }
}
