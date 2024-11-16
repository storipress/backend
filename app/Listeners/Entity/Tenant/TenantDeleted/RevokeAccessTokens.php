<?php

namespace App\Listeners\Entity\Tenant\TenantDeleted;

use App\Events\Entity\Tenant\TenantDeleted;
use App\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class RevokeAccessTokens implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(TenantDeleted $event): void
    {
        $tenant = Tenant::withTrashed()->with('accessToken')->find($event->tenantId);

        if (!($tenant instanceof Tenant)) {
            return;
        }

        $tenant->accessToken?->update(['expires_at' => now()]);
    }
}
