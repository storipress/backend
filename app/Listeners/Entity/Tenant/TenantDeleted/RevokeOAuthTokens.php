<?php

namespace App\Listeners\Entity\Tenant\TenantDeleted;

use App\Events\Entity\Tenant\TenantDeleted;
use App\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class RevokeOAuthTokens implements ShouldQueue
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

        // @todo twitter, facebook, shopify...
        // Not implementing this feature seems to be fine for now
    }
}
