<?php

namespace App\Listeners\Entity\Desk\DeskUpdated;

use App\Events\Entity\Desk\DeskUpdated;
use App\Jobs\WordPress\SyncDeskToWordPress;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class UpdateWordPressCategory implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(DeskUpdated $event): void
    {
        SyncDeskToWordPress::dispatch(
            $event->tenantId,
            $event->deskId,
        );
    }
}
