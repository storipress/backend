<?php

namespace App\Listeners\Entity\Tag\TagUpdated;

use App\Events\Entity\Tag\TagUpdated;
use App\Jobs\Webflow\SyncTagToWebflow;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class UpdateWebflowTagItem implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(TagUpdated $event): void
    {
        SyncTagToWebflow::dispatch(
            $event->tenantId,
            $event->tagId,
        );
    }
}
