<?php

namespace App\Listeners\Partners\WordPress\Webhooks\CategoryDeleted;

use App\Events\Entity\Desk\DeskDeleted;
use App\Events\Partners\WordPress\Webhooks\CategoryDeleted;
use App\Models\Tenant;
use App\Models\Tenants\Desk;
use App\Models\Tenants\UserActivity;
use Illuminate\Contracts\Queue\ShouldQueue;

class DeleteDesk implements ShouldQueue
{
    public function handle(CategoryDeleted $event): void
    {
        $tenant = Tenant::withoutEagerLoads()
            ->initialized()
            ->find($event->tenantId);

        if (! ($tenant instanceof Tenant)) {
            return;
        }

        $tenant->run(function (Tenant $tenant) use ($event) {
            $desk = Desk::withoutEagerLoads()
                ->with(['desk'])
                ->where('wordpress_id', $event->wordpressId)
                ->first();

            if (! ($desk instanceof Desk)) {
                return;
            }

            $desk->update([
                'wordpress_id' => null,
            ]);

            $desk->delete();

            DeskDeleted::dispatch($tenant->id, $desk->id);

            UserActivity::log(
                name: 'wordpress.desk.delete',
                subject: $desk,
                data: [
                    'wordpress_id' => $event->wordpressId,
                ],
                userId: $tenant->owner->id,
            );
        });
    }
}
