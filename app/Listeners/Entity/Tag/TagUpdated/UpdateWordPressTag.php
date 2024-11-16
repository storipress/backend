<?php

namespace App\Listeners\Entity\Tag\TagUpdated;

use App\Events\Entity\Tag\TagUpdated;
use App\Jobs\WordPress\SyncTagToWordPress;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class UpdateWordPressTag implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(TagUpdated $event): void
    {
        SyncTagToWordPress::dispatch(
            $event->tenantId,
            $event->tagId,
        );
    }
}
