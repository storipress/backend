<?php

namespace App\Listeners\Entity\Tag\TagCreated;

use App\Events\Entity\Tag\TagCreated;
use App\Jobs\WordPress\SyncTagToWordPress;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class CreateWordPressTag implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(TagCreated $event): void
    {
        SyncTagToWordPress::dispatch(
            $event->tenantId,
            $event->tagId,
        );
    }
}
