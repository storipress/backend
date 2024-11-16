<?php

namespace App\Listeners\Entity\Desk\DeskCreated;

use App\Events\Entity\Desk\DeskCreated;
use App\Models\Tenant;
use App\Models\Tenants\Desk;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class RelocateParentArticle implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(DeskCreated $event): void
    {
        $tenant = Tenant::find($event->tenantId);

        if (! ($tenant instanceof Tenant)) {
            return;
        }

        $tenant->run(function () use ($event) {
            $desk = Desk::with(['desk'])->find($event->deskId);

            if (! ($desk instanceof Desk)) {
                return;
            }

            if (! ($desk->desk instanceof Desk)) {
                return;
            }

            if ($desk->desk->desks()->count() !== 1) {
                return;
            }

            $desk->desk->articles()->update(['desk_id' => $desk->id]);

            $desk->articles()->chunkById(50, fn ($articles) => $articles->searchable());
        });
    }
}
