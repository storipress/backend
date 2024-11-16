<?php

namespace App\Listeners\Entity\Desk\DeskUserRemoved;

use App\Events\Entity\Desk\DeskUserRemoved;
use App\Jobs\Webflow\SyncDeskToWebflow;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class UpdateWebflowDeskItem implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(DeskUserRemoved $event): void
    {
        SyncDeskToWebflow::dispatch(
            $event->tenantId,
            $event->deskId,
        );
    }
}
