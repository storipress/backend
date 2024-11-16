<?php

namespace App\Listeners\Entity\Tag\TagDeleted;

use App\Events\Entity\Tag\TagDeleted;
use App\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class TriggerSiteBuild implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(TagDeleted $event): void
    {
        $tenant = Tenant::initialized()->find($event->tenantId);

        if (! ($tenant instanceof Tenant)) {
            return;
        }

        if ($tenant->is_ssr) {
            return;
        }

        $tenant->run(function () use ($event) {
            build_site('tag:delete', [
                'id' => $event->tagId,
            ]);
        });
    }
}
