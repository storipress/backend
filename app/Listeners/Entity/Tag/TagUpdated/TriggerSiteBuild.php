<?php

namespace App\Listeners\Entity\Tag\TagUpdated;

use App\Events\Entity\Tag\TagUpdated;
use App\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class TriggerSiteBuild implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(TagUpdated $event): void
    {
        $tenant = Tenant::initialized()->find($event->tenantId);

        if (! ($tenant instanceof Tenant)) {
            return;
        }

        if ($tenant->is_ssr) {
            return;
        }

        if ($tenant->is_ssg) {
            if (empty(array_diff($event->changes, ['name', 'slug', 'description']))) {
                return;
            }
        }

        $tenant->run(function () use ($event) {
            build_site('tag:update', [
                'id' => $event->tagId,
            ]);
        });
    }
}
