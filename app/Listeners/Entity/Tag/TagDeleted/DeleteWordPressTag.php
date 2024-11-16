<?php

namespace App\Listeners\Entity\Tag\TagDeleted;

use App\Events\Entity\Tag\TagDeleted;
use App\Jobs\WordPress\SyncTagToWordPress;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class DeleteWordPressTag implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(TagDeleted $event): void
    {
        SyncTagToWordPress::dispatch(
            $event->tenantId,
            $event->tagId,
        );
    }
}
