<?php

namespace App\Listeners\Entity\Desk\DeskCreated;

use App\Events\Entity\Desk\DeskCreated;
use App\Jobs\Webflow\SyncDeskToWebflow;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class CreateWebflowDeskItem implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(DeskCreated $event): void
    {
        SyncDeskToWebflow::dispatch(
            $event->tenantId,
            $event->deskId,
        );
    }
}
