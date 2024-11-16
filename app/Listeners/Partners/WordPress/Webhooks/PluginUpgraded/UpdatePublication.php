<?php

namespace App\Listeners\Partners\WordPress\Webhooks\PluginUpgraded;

use App\Events\Partners\WordPress\Webhooks\PluginUpgraded;
use App\Listeners\Traits\HasIngestHelper;
use App\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;

class UpdatePublication implements ShouldQueue
{
    use HasIngestHelper;

    public function handle(PluginUpgraded $event): void
    {
        $tenant = Tenant::withoutEagerLoads()
            ->initialized()
            ->find($event->tenantId);

        if (! ($tenant instanceof Tenant)) {
            return;
        }

        $data = $tenant->wordpress_data;

        $data['url'] = $event->payload['url'];

        $data['prefix'] = $event->payload['rest_prefix'];

        $data['is_pretty_url'] = is_not_empty_string($event->payload['permalink_structure']);

        $tenant->wordpress_data = $data;

        $tenant->save();

        $this->ingest($event);
    }
}
