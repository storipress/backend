<?php

namespace App\Listeners\Partners\WordPress\Connected;

use App\Events\Partners\WordPress\Connected;
use App\Listeners\Traits\HasIngestHelper;
use App\Models\Tenant;
use App\Models\Tenants\Integrations\WordPress;

class SetupIntegration
{
    use HasIngestHelper;

    /**
     * Handle the event.
     */
    public function handle(Connected $event): void
    {
        $tenant = Tenant::withoutEagerLoads()
            ->initialized()
            ->find($event->tenantId);

        if (! ($tenant instanceof Tenant)) {
            return;
        }

        $tenant->run(function () use ($event) {
            $now = now();

            WordPress::retrieve()->update([
                'internals' => [
                    ...$event->payload,
                    'expired' => false,
                ],
                'activated_at' => $now,
                'updated_at' => $now,
            ]);
        });

        $this->ingest($event);
    }
}
