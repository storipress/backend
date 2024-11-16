<?php

namespace App\Listeners\Entity\Tenant\UserJoined;

use App\Events\Entity\Tenant\UserJoined;
use App\Jobs\WordPress\SyncUserToWordPress;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class CreateWordPressUser implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(UserJoined $event): void
    {
        SyncUserToWordPress::dispatch(
            $event->tenantId,
            $event->userId,
        );
    }
}
