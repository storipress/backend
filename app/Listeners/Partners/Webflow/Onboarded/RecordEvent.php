<?php

declare(strict_types=1);

namespace App\Listeners\Partners\Webflow\Onboarded;

use App\Events\Partners\Webflow\Onboarded;
use App\Listeners\Traits\HasIngestHelper;

class RecordEvent
{
    use HasIngestHelper;

    /**
     * Handle the event.
     */
    public function handle(Onboarded $event): void
    {
        $this->ingest($event);
    }
}
