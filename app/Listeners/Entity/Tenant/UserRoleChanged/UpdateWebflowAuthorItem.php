<?php

namespace App\Listeners\Entity\Tenant\UserRoleChanged;

use App\Events\Entity\Tenant\UserRoleChanged;
use App\Jobs\Webflow\SyncUserToWebflow;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class UpdateWebflowAuthorItem implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(UserRoleChanged $event): void
    {
        SyncUserToWebflow::dispatch(
            $event->tenantId,
            $event->userId,
        );
    }
}
