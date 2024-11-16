<?php

namespace App\Listeners\Entity\Page\PageDeleted;

use App\Events\Entity\Page\PageDeleted;
use App\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class TriggerSiteBuild implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(PageDeleted $event): void
    {
        $tenant = Tenant::initialized()->find($event->tenantId);

        if (!($tenant instanceof Tenant)) {
            return;
        }

        if ($tenant->is_ssr) {
            return;
        }

        $tenant->run(function () use ($event) {
            build_site('page:delete', [
                'id' => $event->pageId,
            ]);
        });
    }
}
