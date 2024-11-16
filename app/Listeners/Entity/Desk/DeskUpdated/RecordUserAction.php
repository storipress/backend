<?php

namespace App\Listeners\Entity\Desk\DeskUpdated;

use App\Events\Entity\Desk\DeskUpdated;
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
    public function handle(DeskUpdated $event): void
    {
        $tenant = Tenant::find($event->tenantId);

        if (!($tenant instanceof Tenant)) {
            return;
        }

        Segment::track([
            'userId' => (string) ($event->authId ?: $tenant->user_id),
            'event' => 'tenant_desk_updated',
            'properties' => [
                'tenant_uid' => $tenant->id,
                'tenant_name' => $tenant->name,
                'tenant_desk_uid' => (string) $event->deskId,
            ],
            'context' => [
                'groupId' => $tenant->id,
            ],
        ]);
    }
}
