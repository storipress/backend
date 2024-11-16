<?php

declare(strict_types=1);

namespace App\Listeners\Partners\Webflow\OAuthDisconnected;

use App\Events\Partners\Webflow\OAuthDisconnected;
use App\Listeners\Traits\HasIngestHelper;
use App\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class TriggerSiteBuild implements ShouldQueue
{
    use HasIngestHelper;
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(OAuthDisconnected $event): void
    {
        $tenant = Tenant::initialized()
            ->find($event->tenantId);

        if (!($tenant instanceof Tenant)) {
            return;
        }

        $tenant->run(function () {
            build_site('webflow:disconnect');
        });

        $this->ingest($event);
    }
}
