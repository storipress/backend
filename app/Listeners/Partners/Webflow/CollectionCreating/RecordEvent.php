<?php

declare(strict_types=1);

namespace App\Listeners\Partners\Webflow\CollectionCreating;

use App\Events\Partners\Webflow\CollectionCreating;
use App\Listeners\Traits\HasIngestHelper;

class RecordEvent
{
    use HasIngestHelper;

    /**
     * Handle the event.
     */
    public function handle(CollectionCreating $event): void
    {
        $this->ingest($event);
    }
}
