<?php

namespace App\Listeners\Entity\Tag\TagDeleted;

use App\Events\Entity\Tag\TagDeleted;
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
    public function handle(TagDeleted $event): void
    {
        $tenant = Tenant::find($event->tenantId);

        if (! ($tenant instanceof Tenant)) {
            return;
        }

        Segment::track([
            'userId' => (string) ($event->authId ?: $tenant->user_id),
            'event' => 'tenant_tag_deleted',
            'properties' => [
                'tenant_uid' => $tenant->id,
                'tenant_name' => $tenant->name,
                'tenant_tag_uid' => (string) $event->tagId,
            ],
            'context' => [
                'groupId' => $tenant->id,
            ],
        ]);
    }
}
