<?php

namespace App\Listeners\Entity\Domain\CustomDomainRemoved;

use App\Enums\CustomDomain\Group;
use App\Events\Entity\Domain\CustomDomainRemoved;
use App\Models\Tenant;

class CleanupCustomDomain
{
    /**
     * Handle the event.
     */
    public function handle(CustomDomainRemoved $event): void
    {
        $tenant = Tenant::withTrashed()->find($event->tenantId);

        if (!($tenant instanceof Tenant)) {
            return;
        }

        $tenant->update([
            'custom_domain' => null,
            'site_domain' => null,
        ]);

        $tenant->custom_domains()
            ->whereIn('group', [
                Group::site(),
                Group::redirect(),
            ])
            ->delete();
    }
}
