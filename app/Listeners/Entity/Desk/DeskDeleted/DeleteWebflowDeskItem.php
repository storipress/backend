<?php

namespace App\Listeners\Entity\Desk\DeskDeleted;

use App\Events\Entity\Desk\DeskDeleted;
use App\Models\Tenant;
use App\Models\Tenants\Desk;
use App\Models\Tenants\Integrations\Webflow;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class DeleteWebflowDeskItem implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(DeskDeleted $event): void
    {
        $tenant = Tenant::withoutEagerLoads()
            ->initialized()
            ->find($event->tenantId);

        if (! ($tenant instanceof Tenant)) {
            return;
        }

        $tenant->run(function (Tenant $tenant) use ($event) {
            $webflow = Webflow::retrieve();

            if (! $webflow->is_activated) {
                return;
            }

            $collection = $webflow->config->collections['desk'] ?? null;

            if (! is_array($collection)) {
                return; // @todo webflow - logging
            }

            $desk = Desk::onlyTrashed()
                ->withoutEagerLoads()
                ->find($event->deskId);

            if (! ($desk instanceof Desk)) {
                return;
            }

            if (! is_not_empty_string($desk->webflow_id)) {
                return; // @todo webflow - something went wrong
            }

            $slug = sprintf('%s-%d', $desk->slug, now()->timestamp);

            app('webflow')->item()->update(
                $collection['id'],
                $desk->webflow_id,
                [
                    'isArchived' => true,
                    'isDraft' => false,
                    'fieldData' => [
                        'slug' => $slug,
                    ],
                ],
                true,
            );
        });
    }
}
