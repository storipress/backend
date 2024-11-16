<?php

namespace App\Listeners\Entity\Block\BlockDeleted;

use App\Events\Entity\Block\BlockDeleted;
use App\Models\Tenant;
use App\Models\Tenants\Block;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class TriggerSiteBuild implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(BlockDeleted $event): void
    {
        $tenant = Tenant::initialized()->find($event->tenantId);

        if (! ($tenant instanceof Tenant)) {
            return;
        }

        $tenant->run(function () use ($event) {
            $block = Block::onlyTrashed()->find($event->blockId);

            if (! ($block instanceof Block)) {
                return;
            }

            build_site('block:delete', [
                'id' => $event->blockId,
            ]);
        });
    }
}
