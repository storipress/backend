<?php

namespace App\Listeners\Entity\Domain\CustomDomainEnabled;

use App\Events\Entity\Domain\CustomDomainEnabled;
use App\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class EnsureBackwardCompatibility implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(CustomDomainEnabled $event): void
    {
        $tenant = Tenant::find($event->tenantId);

        if (! ($tenant instanceof Tenant)) {
            return;
        }

        if (empty($tenant->site_domain)) {
            return;
        }

        $tenant->update([
            'custom_domain' => $tenant->site_domain,
        ]);
    }
}
