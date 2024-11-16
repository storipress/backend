<?php

namespace App\Listeners\Partners\WordPress\Connected;

use App\Events\Partners\WordPress\Connected;
use App\Listeners\Traits\HasIngestHelper;
use App\Models\Tenant;

class SetupPublication
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

        $tenant->update([
            'wordpress_data' => [
                'username' => $event->payload['username'],
                'access_token' => $event->payload['access_token'],
                'hash_key' => $event->payload['hash_key'],
                'url' => $event->payload['url'],
                'prefix' => $event->payload['prefix'] ?? '',
                'is_pretty_url' => is_not_empty_string($event->payload['permalink_structure']),
            ],
        ]);

        $this->ingest($event);
    }
}
