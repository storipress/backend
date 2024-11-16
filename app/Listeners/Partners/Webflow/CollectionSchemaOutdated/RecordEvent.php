<?php

namespace App\Listeners\Partners\Webflow\CollectionSchemaOutdated;

use App\Events\Partners\Webflow\CollectionSchemaOutdated;
use App\Listeners\Traits\HasIngestHelper;

class RecordEvent
{
    use HasIngestHelper;

    /**
     * Handle the event.
     */
    public function handle(CollectionSchemaOutdated $event): void
    {
        $this->ingest($event);
    }
}
