<?php

namespace App\Listeners\Entity\Tag\TagCreated;

use App\Events\Entity\Tag\TagCreated;
use App\Jobs\Webflow\SyncTagToWebflow;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class CreateWebflowTagItem implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(TagCreated $event): void
    {
        SyncTagToWebflow::dispatch(
            $event->tenantId,
            $event->tagId,
        );
    }
}
