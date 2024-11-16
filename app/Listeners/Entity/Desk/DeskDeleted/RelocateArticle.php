<?php

namespace App\Listeners\Entity\Desk\DeskDeleted;

use App\Events\Entity\Desk\DeskDeleted;
use App\Models\Tenant;
use App\Models\Tenants\Desk;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class RelocateArticle implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(DeskDeleted $event): void
    {
        $tenant = Tenant::initialized()->find($event->tenantId);

        if (! ($tenant instanceof Tenant)) {
            return;
        }

        $tenant->run(function () use ($event) {
            $desk = Desk::onlyTrashed()
                ->with(['desk'])
                ->find($event->deskId);

            if (! ($desk instanceof Desk)) {
                return;
            }

            if (! ($desk->desk instanceof Desk)) {
                return;
            }

            if ($desk->desk->desks()->count() !== 0) {
                return;
            }

            $desk->articles()->update(['desk_id' => $desk->desk->id]);

            $desk->desk->articles()->chunkById(
                50,
                fn ($articles) => $articles->searchable(),
            );
        });
    }
}
