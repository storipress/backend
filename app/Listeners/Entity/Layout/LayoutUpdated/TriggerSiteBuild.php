<?php

namespace App\Listeners\Entity\Layout\LayoutUpdated;

use App\Events\Entity\Layout\LayoutUpdated;
use App\Models\Tenant;
use App\Models\Tenants\Layout;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class TriggerSiteBuild implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(LayoutUpdated $event): void
    {
        $tenant = Tenant::initialized()->find($event->tenantId);

        if (! ($tenant instanceof Tenant)) {
            return;
        }

        if (empty(array_intersect($event->changes, ['template', 'data']))) {
            return;
        }

        $tenant->run(function () use ($event) {
            $layout = Layout::find($event->layoutId);

            if (! ($layout instanceof Layout)) {
                return;
            }

            $inUsed = $layout->articles()->exists() ||
                $layout->desks()->exists() ||
                $layout->pages()->exists();

            if (! $inUsed) {
                return;
            }

            build_site('layout:update', [
                'id' => $event->layoutId,
            ]);
        });
    }
}
