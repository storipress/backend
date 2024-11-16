<?php

namespace App\Listeners\Entity\Page\PageUpdated;

use App\Events\Entity\Page\PageUpdated;
use App\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class TriggerSiteBuild implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(PageUpdated $event): void
    {
        $tenant = Tenant::initialized()->find($event->tenantId);

        if (! ($tenant instanceof Tenant)) {
            return;
        }

        if ($tenant->is_ssg) {
            if (empty(array_diff($event->changes, ['draft']))) {
                return;
            }
        }

        if ($tenant->is_ssr) {
            if (! in_array('seo', $event->changes, true)) {
                return;
            }
        }

        $tenant->run(function () use ($event) {
            build_site('page:update', [
                'id' => $event->pageId,
            ]);
        });
    }
}
