<?php

namespace App\Listeners\Entity\Tenant\UserRoleChanged;

use App\Events\Entity\Tenant\UserRoleChanged;
use App\Jobs\Webflow\SyncDeskToWebflow;
use App\Models\Tenant;
use App\Models\Tenants\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class UpdateWebflowDeskItem implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(UserRoleChanged $event): void
    {
        $tenant = Tenant::withoutEagerLoads()
            ->initialized()
            ->find($event->tenantId);

        if (! ($tenant instanceof Tenant)) {
            return;
        }

        $tenant->run(function () use ($event) {
            $user = User::withoutEagerLoads()
                ->with(['desks'])
                ->find($event->userId);

            if (! ($user instanceof User)) {
                return;
            }

            foreach ($user->desks as $desk) {
                SyncDeskToWebflow::dispatch(
                    $event->tenantId,
                    $desk->id,
                );
            }
        });
    }
}
