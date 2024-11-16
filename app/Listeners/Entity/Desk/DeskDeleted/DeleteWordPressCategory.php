<?php

namespace App\Listeners\Entity\Desk\DeskDeleted;

use App\Events\Entity\Desk\DeskDeleted;
use App\Jobs\WordPress\SyncDeskToWordPress;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class DeleteWordPressCategory implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(DeskDeleted $event): void
    {
        SyncDeskToWordPress::dispatch(
            $event->tenantId,
            $event->deskId,
        );
    }
}
