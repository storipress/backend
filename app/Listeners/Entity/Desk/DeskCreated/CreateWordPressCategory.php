<?php

namespace App\Listeners\Entity\Desk\DeskCreated;

use App\Events\Entity\Desk\DeskCreated;
use App\Jobs\WordPress\SyncDeskToWordPress;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class CreateWordPressCategory implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(DeskCreated $event): void
    {
        SyncDeskToWordPress::dispatch(
            $event->tenantId,
            $event->deskId,
        );
    }
}
