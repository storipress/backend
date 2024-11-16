<?php

namespace App\Listeners\Entity\Desk\DeskUpdated;

use App\Events\Entity\Desk\DeskUpdated;
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
    public function handle(DeskUpdated $event): void
    {
        $tenant = Tenant::initialized()->find($event->tenantId);

        if (!($tenant instanceof Tenant)) {
            return;
        }

        if ($tenant->is_ssg) {
            $tracks = ['desk_id', 'layout_id', 'name', 'slug', 'seo', 'order'];

            if (empty(array_intersect($event->changes, $tracks))) {
                return;
            }
        }

        if ($tenant->is_ssr) {
            if (!in_array('seo', $event->changes, true)) {
                return;
            }
        }

        $tenant->run(function () use ($event) {
            $desk = Desk::find($event->deskId);

            if (!($desk instanceof Desk)) {
                return;
            }

            if ($desk->total_articles_count === 0) {
                return;
            }

            build_site('desk:update', [
                'id' => $event->deskId,
            ]);
        });
    }
}
