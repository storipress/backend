<?php

namespace App\Listeners\Entity\Desk\DeskOrderChanged;

use App\Events\Entity\Desk\DeskOrderChanged;
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
    public function handle(DeskOrderChanged $event): void
    {
        $tenant = Tenant::find($event->tenantId);

        if (!($tenant instanceof Tenant)) {
            return;
        }

        Segment::track([
            'userId' => (string) ($event->authId ?: $tenant->user_id),
            'event' => 'tenant_desk_order_changed',
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
