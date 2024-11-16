<?php

namespace App\Listeners\Entity\Tenant\TenantDeleted;

use App\Events\Entity\Tenant\TenantDeleted;
use App\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class CleanupAssets implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(TenantDeleted $event): void
    {
        $tenant = Tenant::withTrashed()->find($event->tenantId);

        if (!($tenant instanceof Tenant)) {
            return;
        }

        // @todo implement at media library may be more suitable
    }
}
