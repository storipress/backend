<?php

namespace App\Listeners\Partners\WordPress\Disconnected;

use App\Events\Partners\WordPress\Disconnected;
use App\Listeners\Traits\HasIngestHelper;
use App\Models\Tenant;
use App\Models\Tenants\Integration;

class CleanupIntegration
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

        $tenant->run(function () {
            Integration::where('key', '=', 'wordpress')->update([
                'data' => [],
                'internals' => null,
                'activated_at' => null,
            ]);
        });

        $this->ingest($event);
    }
}
