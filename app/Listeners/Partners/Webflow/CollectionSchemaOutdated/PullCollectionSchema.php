<?php

namespace App\Listeners\Partners\Webflow\CollectionSchemaOutdated;

use App\Enums\Webflow\CollectionType;
use App\Events\Partners\Webflow\CollectionSchemaOutdated;
use App\GraphQL\Mutations\Webflow\UpdateWebflowCollectionMapping;
use App\Listeners\Traits\HasIngestHelper;
use App\Models\Tenant;
use App\Models\Tenants\Integrations\Webflow;
use App\Notifications\Webflow\WebflowSchemaChangedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class PullCollectionSchema implements ShouldQueue
{
    use HasIngestHelper;
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(CollectionSchemaOutdated $event): void
    {
        $tenant = Tenant::withoutEagerLoads()
            ->with(['owner'])
            ->initialized()
            ->find($event->tenantId);

        if (! ($tenant instanceof Tenant)) {
            return;
        }

        $tenant->run(function (Tenant $tenant) {
            $webflow = Webflow::retrieve();

            if (! $webflow->is_activated) {
                return;
            }

            $helper = new UpdateWebflowCollectionMapping();

            $collections = [];

            $changed = false;

            foreach ($webflow->config->collections as $key => $item) {
                $collection = app('webflow')
                    ->collection()
                    ->get($item['id']);

                $encode = json_encode($collection);

                if ($encode === false) {
                    return;
                }

                $collection = json_decode($encode, true);

                if (! is_array($collection)) {
                    return;
                }

                $fields = $collection['fields'];

                $itemIds = array_column($fields, 'id');

                $mappings = $item['mappings'] ?? [];

                if (count($itemIds) !== count($mappings)) {
                    $changed = true;
                }

                foreach ($mappings as $itemId => &$sp) {
                    if (! in_array($itemId, $itemIds, true)) {
                        $sp = null;
                    }
                }

                if ($group = $helper->group(CollectionType::fromValue($key))) {
                    foreach ($fields as $field) {
                        if (array_key_exists($field->id, $mappings)) {
                            continue;
                        }

                        $mappings[$field->id] = sprintf(
                            'custom_fields.%d',
                            $helper->toCustomField($group, $field)->id,
                        );
                    }
                }

                $collections[$key] = $collection;

                $collections[$key]['mappings'] = $mappings;
            }

            $webflow->config->update([
                'collections' => $collections,
            ]);

            if ($changed) {
                $tenant->owner->notify(
                    new WebflowSchemaChangedNotification(
                        $tenant->id,
                        $tenant->name,
                    ),
                );
            }
        });

        $this->ingest($event);
    }
}
