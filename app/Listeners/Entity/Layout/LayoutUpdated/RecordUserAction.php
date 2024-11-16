<?php

namespace App\Listeners\Entity\Layout\LayoutUpdated;

use App\Events\Entity\Layout\LayoutUpdated;
use App\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Segment\Segment;

class RecordUserAction implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(LayoutUpdated $event): void
    {
        $tenant = Tenant::find($event->tenantId);

        if (!($tenant instanceof Tenant)) {
            return;
        }

        Segment::track([
            'userId' => (string) ($event->authId ?: $tenant->user_id),
            'event' => 'tenant_layout_updated',
            'properties' => [
                'tenant_uid' => $tenant->id,
                'tenant_name' => $tenant->name,
                'tenant_layout_uid' => (string) $event->layoutId,
            ],
            'context' => [
                'groupId' => $tenant->id,
            ],
        ]);
    }
}
