<?php

declare(strict_types=1);

namespace App\Listeners\Partners\Webflow\OAuthConnecting;

use App\Events\Partners\Webflow\OAuthConnecting;
use App\Listeners\Traits\HasIngestHelper;

class RecordEvent
{
    use HasIngestHelper;

    /**
     * Handle the event.
     */
    public function handle(OAuthConnecting $event): void
    {
        $this->ingest($event);
    }
}
