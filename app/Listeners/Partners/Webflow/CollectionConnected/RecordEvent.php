<?php

declare(strict_types=1);

namespace App\Listeners\Partners\Webflow\CollectionConnected;

use App\Events\Partners\Webflow\CollectionConnected;
use App\Listeners\Traits\HasIngestHelper;

class RecordEvent
{
    use HasIngestHelper;

    /**
     * Handle the event.
     */
    public function handle(CollectionConnected $event): void
    {
        $this->ingest($event);
    }
}
