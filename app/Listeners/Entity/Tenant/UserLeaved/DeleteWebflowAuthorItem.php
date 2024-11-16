<?php

namespace App\Listeners\Entity\Tenant\UserLeaved;

use App\Events\Entity\Tenant\UserLeaved;
use App\Models\Tenant;
use App\Models\Tenants\Integrations\Webflow;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Storipress\Webflow\Exceptions\HttpConflict;
use Storipress\Webflow\Exceptions\HttpNotFound;

class DeleteWebflowAuthorItem implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(UserLeaved $event): void
    {
        $tenant = Tenant::withoutEagerLoads()
            ->initialized()
            ->find($event->tenantId);

        if (! ($tenant instanceof Tenant)) {
            return;
        }

        if (! is_not_empty_string($event->data['webflow_id'])) {
            return;
        }

        if (! is_not_empty_string($event->data['slug'])) {
            return;
        }

        $tenant->run(function () use ($event) {
            $webflow = Webflow::retrieve();

            if (! $webflow->is_activated) {
                return;
            }

            $collection = $webflow->config->collections['author'] ?? null;

            if (! is_array($collection)) {
                return;
            }

            $api = app('webflow')->item();

            $collectionId = $collection['id'];

            $itemId = $event->data['webflow_id'];

            $data = [
                'isArchived' => true,
                'isDraft' => false,
                'fieldData' => [
                    'slug' => sprintf('%s-%d', $event->data['slug'], now()->timestamp),
                ],
            ];

            try {
                $api->delete($collectionId, $itemId, true);
            } catch (HttpConflict) {
                try {
                    $api->update($collectionId, $itemId, $data, true);
                } catch (HttpConflict) {
                    $api->update($collectionId, $itemId, $data);
                }
            } catch (HttpNotFound) {
                // ignored
            }
        });
    }
}
