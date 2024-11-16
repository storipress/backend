<?php

namespace App\Listeners\Entity\Domain\CustomDomainRemoved;

use App\Builder\ReleaseEventsBuilder;
use App\Events\Entity\Domain\CustomDomainRemoved;
use App\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class RebuildPublicationSite implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(CustomDomainRemoved $event): void
    {
        $tenant = Tenant::find($event->tenantId);

        if (!($tenant instanceof Tenant)) {
            return;
        }

        $tenant->run(
            fn () => (new ReleaseEventsBuilder())->handle(
                'domain:disable',
                ['domain' => $tenant->site_domain],
            ),
        );
    }
}
