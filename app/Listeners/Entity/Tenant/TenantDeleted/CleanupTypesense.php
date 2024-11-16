<?php

namespace App\Listeners\Entity\Tenant\TenantDeleted;

use App\Events\Entity\Tenant\TenantDeleted;
use App\Models\Tenant;
use App\Models\Tenants\Article;
use App\Models\Tenants\Subscriber;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class CleanupTypesense implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(TenantDeleted $event): void
    {
        $tenant = Tenant::withTrashed()->find($event->tenantId);

        if (! ($tenant instanceof Tenant)) {
            return;
        }

        $tenant->run(function () {
            Article::removeAllFromSearch();

            Subscriber::removeAllFromSearch();
        });
    }
}
