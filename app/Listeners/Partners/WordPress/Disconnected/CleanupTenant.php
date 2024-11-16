<?php

namespace App\Listeners\Partners\WordPress\Disconnected;

use App\Events\Partners\WordPress\Disconnected;
use App\Listeners\Traits\HasIngestHelper;
use App\Models\Tenant;

class CleanupTenant
{
    use HasIngestHelper;

    /**
     * Handle the event.
     */
    public function handle(Disconnected $event): void
    {
        $tenant = Tenant::withoutEagerLoads()
            ->initialized()
            ->find($event->tenantId);

        if (! ($tenant instanceof Tenant)) {
            return;
        }

        $tenant->update(['wordpress_data' => null]);

        $this->ingest($event);
    }
}
