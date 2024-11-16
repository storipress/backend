<?php

namespace App\Listeners\Entity\Domain\CustomDomainEnabled;

use App\Builder\ReleaseEventsBuilder;
use App\Events\Entity\Domain\CustomDomainEnabled;
use App\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class RebuildPublicationSite implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(CustomDomainEnabled $event): void
    {
        $tenant = Tenant::find($event->tenantId);

        if (!($tenant instanceof Tenant)) {
            return;
        }

        if (empty($tenant->site_domain)) {
            return;
        }

        $tenant->run(
            fn () => (new ReleaseEventsBuilder())->handle(
                'domain:enable',
                ['domain' => $tenant->site_domain],
            ),
        );
    }
}
