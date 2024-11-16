<?php

namespace App\Listeners\Partners\Revert\HubSpotOAuthConnected;

use App\Events\Partners\Revert\HubSpotOAuthConnected;
use App\Listeners\Traits\HasIngestHelper;

class RecordEvent
{
    use HasIngestHelper;

    /**
     * Handle the event.
     */
    public function handle(HubSpotOAuthConnected $event): void
    {
        $this->ingest($event);
    }
}
