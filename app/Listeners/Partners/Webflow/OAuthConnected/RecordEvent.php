<?php

declare(strict_types=1);

namespace App\Listeners\Partners\Webflow\OAuthConnected;

use App\Events\Partners\Webflow\OAuthConnected;
use App\Listeners\Traits\HasIngestHelper;

class RecordEvent
{
    use HasIngestHelper;

    /**
     * Handle the event.
     */
    public function handle(OAuthConnected $event): void
    {
        $this->ingest($event);
    }
}
