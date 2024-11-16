<?php

namespace App\Listeners\Entity\Tenant\UserJoined;

use App\Events\Entity\Desk\DeskUserAdded;
use App\Events\Entity\Tenant\UserJoined;
use App\Jobs\Webflow\SyncUserToWebflow;
use App\Models\Tenant;
use App\Models\Tenants\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class CreateWebflowAuthorItem implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(UserJoined $event): void
    {
        $tenant = Tenant::withoutEagerLoads()
            ->initialized()
            ->find($event->tenantId);

        if (! ($tenant instanceof Tenant)) {
            return;
        }

        $tenant->run(function (Tenant $tenant) use ($event) {
            $user = User::withoutEagerLoads()
                ->with(['desks'])
                ->find($event->userId);

            if (! ($user instanceof User)) {
                return;
            }

            foreach ($user->desks as $desk) {
                DeskUserAdded::dispatch(
                    $tenant->id,
                    $desk->id,
                    $user->id,
                );
            }

            SyncUserToWebflow::dispatch(
                $event->tenantId,
                $event->userId,
            );
        });
    }
}
