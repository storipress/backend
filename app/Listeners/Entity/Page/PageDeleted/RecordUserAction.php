<?php

namespace App\Listeners\Entity\Page\PageDeleted;

use App\Events\Entity\Page\PageDeleted;
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
    public function handle(PageDeleted $event): void
    {
        $tenant = Tenant::find($event->tenantId);

        if (! ($tenant instanceof Tenant)) {
            return;
        }

        Segment::track([
            'userId' => (string) ($event->authId ?: $tenant->user_id),
            'event' => 'tenant_page_deleted',
            'properties' => [
                'tenant_uid' => $tenant->id,
                'tenant_name' => $tenant->name,
                'tenant_page_uid' => (string) $event->pageId,
            ],
            'context' => [
                'groupId' => $tenant->id,
            ],
        ]);
    }
}
