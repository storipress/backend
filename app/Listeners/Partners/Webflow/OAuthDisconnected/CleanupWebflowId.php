<?php

namespace App\Listeners\Partners\Webflow\OAuthDisconnected;

use App\Events\Partners\Webflow\OAuthDisconnected;
use App\Listeners\Traits\HasIngestHelper;
use App\Models\Tenant;
use App\Models\Tenants\Article;
use App\Models\Tenants\Desk;
use App\Models\Tenants\Tag;
use App\Models\Tenants\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class CleanupWebflowId implements ShouldQueue
{
    use HasIngestHelper;
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(OAuthDisconnected $event): void
    {
        $tenant = Tenant::withoutEagerLoads()
            ->initialized()
            ->find($event->tenantId);

        if (!($tenant instanceof Tenant)) {
            return;
        }

        $tenant->run(function () {
            Article::withTrashed()->whereNotNull('webflow_id')->update(['webflow_id' => null]);

            Desk::withTrashed()->whereNotNull('webflow_id')->update(['webflow_id' => null]);

            Tag::withTrashed()->whereNotNull('webflow_id')->update(['webflow_id' => null]);

            User::whereNotNull('webflow_id')->update(['webflow_id' => null]);
        });

        $this->ingest($event);
    }
}
