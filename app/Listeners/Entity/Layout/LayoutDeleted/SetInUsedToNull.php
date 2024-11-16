<?php

namespace App\Listeners\Entity\Layout\LayoutDeleted;

use App\Events\Entity\Layout\LayoutDeleted;
use App\Models\Tenant;
use App\Models\Tenants\Layout;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SetInUsedToNull implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(LayoutDeleted $event): void
    {
        $tenant = Tenant::find($event->tenantId);

        if (!($tenant instanceof Tenant)) {
            return;
        }

        $tenant->run(function () use ($event) {
            $layout = Layout::onlyTrashed()->find($event->layoutId);

            if (!($layout instanceof Layout)) {
                return;
            }

            $layout->articles()->update(['layout_id' => null]);

            $layout->desks()->update(['layout_id' => null]);

            $layout->pages()->update(['layout_id' => null]);
        });
    }
}
