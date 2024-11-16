<?php

declare(strict_types=1);

namespace App\Listeners\Partners\Webflow\OAuthDisconnected;

use App\Events\Partners\Webflow\OAuthDisconnected;
use App\Listeners\Traits\HasIngestHelper;

class RecordEvent
{
    use HasIngestHelper;

    /**
     * Handle the event.
     */
    public function handle(OAuthDisconnected $event): void
    {
        $this->ingest($event);
    }
}
