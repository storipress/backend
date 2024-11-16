<?php

namespace App\Listeners\Partners\WordPress\Webhooks\PluginUpgraded;

use App\Events\Partners\WordPress\Webhooks\PluginUpgraded;
use App\Listeners\Traits\HasIngestHelper;
use App\Models\Tenant;
use App\Models\Tenants\Integrations\WordPress;
use Illuminate\Contracts\Queue\ShouldQueue;

class UpdateIntegration implements ShouldQueue
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

        $tenant->run(function (Tenant $tenant) use ($event) {
            $wordpress = WordPress::retrieve();

            if (! $wordpress->is_connected) {
                return;
            }

            $wordpress->config->update([
                'version' => $event->payload['version'],
                'url' => $event->payload['url'],
                'site_name' => $event->payload['site_name'],
                'prefix' => $event->payload['rest_prefix'],
                'permalink_structure' => $event->payload['permalink_structure'],
                'feature' => [
                    'yoast_seo' => $event->payload['activated_plugins']['yoast_seo'],
                    'acf' => $event->payload['activated_plugins']['acf'],
                ],
            ]);

            $this->ingest($event);
        });
    }
}
