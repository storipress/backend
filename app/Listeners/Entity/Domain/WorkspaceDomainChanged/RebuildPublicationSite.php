<?php

namespace App\Listeners\Entity\Domain\WorkspaceDomainChanged;

use App\Builder\ReleaseEventsBuilder;
use App\Events\Entity\Domain\WorkspaceDomainChanged;
use App\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class RebuildPublicationSite implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(WorkspaceDomainChanged $event): void
    {
        $tenant = Tenant::find($event->tenantId);

        if (!($tenant instanceof Tenant)) {
            return;
        }

        $tenant->run(
            fn () => (new ReleaseEventsBuilder())->handle(
                'workspace:update',
                [
                    'new' => $tenant->workspace,
                    'old' => $event->origin,
                ],
            ),
        );
    }
}
