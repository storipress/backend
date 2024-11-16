<?php

declare(strict_types=1);

namespace App\Listeners\Partners\Webflow\CollectionCreating;

use App\Events\Partners\Webflow\CollectionCreating;
use App\Listeners\Traits\HasIngestHelper;
use App\Models\Tenant;
use App\Models\Tenants\Integrations\Webflow;
use App\Notifications\Webflow\WebflowPlanUpgradeNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Str;
use Storipress\Webflow\Exceptions\HttpConflict;
use Storipress\Webflow\Requests\CollectionField;

/**
 * @phpstan-import-type CollectionFieldCreateType from CollectionField as WebflowFieldType
 */
abstract class CreateCollection implements ShouldQueue
{
    use HasIngestHelper;
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(CollectionCreating $event): void
    {
        $tenant = Tenant::withoutEagerLoads()
            ->with(['owner'])
            ->initialized()
            ->find($event->tenantId);

        if (! ($tenant instanceof Tenant)) {
            return;
        }

        $tenant->run(function (Tenant $tenant) use ($event): void {
            $webflow = Webflow::retrieve();

            if (! $webflow->is_connected) {
                return;
            }

            $siteId = $webflow->config->site_id;

            if (! is_not_empty_string($siteId)) {
                return; // @todo webflow - something went wrong
            }

            $type = $event->collectionType->value;

            if (! is_not_empty_string($type)) {
                return; // @todo webflow - something went wrong
            }

            $api = app('webflow')->collection();

            $mappings = [];

            $displayName = Str::of($type)->plural()->title()->value();

            $singularName = Str::of($type)->singular()->title()->value();

            try {
                $collection = $api->create($siteId, $displayName, $singularName, $type);
            } catch (HttpConflict $e) {
                $message = $e->getMessage();

                if (Str::contains($message, 'duplicate_collection', true)) {
                    foreach ($api->list($siteId) as $item) {
                        if ($item->slug === $type || $item->displayName === $displayName || $item->singularName === $singularName) {
                            $collection = $api->get($item->id);
                        }
                    }
                } elseif (Str::contains($message, 'Upgrade to CMS Hosting to use CMS features', true)) {
                    $webflow->config->update(['expired' => true]);

                    $tenant->owner->notify(
                        new WebflowPlanUpgradeNotification(
                            $tenant->id,
                            $tenant->name,
                        ),
                    );

                    return;
                }

                if (! isset($collection)) {
                    throw $e;
                }
            }

            foreach ($collection->fields as $field) {
                if ($field->slug === 'slug') {
                    $mappings[$field->id] = 'slug';
                } elseif ($field->slug === 'name') {
                    $mappings[$field->id] = $event->collectionType->is('blog') ? 'title' : 'name';
                }
            }

            foreach ($this->fields() as $field) {
                $object = app('webflow')->collectionField()->create(
                    $collection->id,
                    [
                        'displayName' => $field['displayName'],
                        'type' => $field['type'],
                        'isRequired' => false,
                    ],
                );

                $mappings[$object->id] = $field['key'];
            }

            $webflow->config->update([
                'onboarding' => [
                    'collection' => [
                        $type => true,
                    ],
                ],
                'collections' => [
                    $type => app('webflow')->collection()->get($collection->id),
                ],
            ]);

            $webflow->config->update([
                'onboarding' => [
                    'mapping' => [
                        $type => true,
                    ],
                ],
                'collections' => [
                    $type => [
                        'mappings' => $mappings,
                    ],
                ],
            ]);

            $this->ingest($event, ['collection_type' => $type]);
        });
    }

    /**
     * @return array<int, array{
     *     displayName: non-empty-string,
     *     type: WebflowFieldType,
     *     key: string,
     * }>
     */
    abstract public function fields(): array;
}
