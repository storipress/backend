<?php

namespace App\Listeners\Entity\Block\BlockUpdated;

use App\Events\Entity\Block\BlockUpdated;
use App\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class TriggerSiteBuild implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(BlockUpdated $event): void
    {
        $tenant = Tenant::initialized()->find($event->tenantId);

        if (!($tenant instanceof Tenant)) {
            return;
        }

        $tenant->run(function () use ($event) {
            build_site('block:update', [
                'id' => $event->blockId,
            ]);
        });
    }
}
