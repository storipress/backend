<?php

declare(strict_types=1);

namespace App\Jobs\Webflow;

use App\Models\Tenant;
use App\Models\Tenants\Integrations\Webflow;
use App\Models\Tenants\Tag;
use RuntimeException;

class SyncTagToWebflow extends WebflowSyncJob
{
    /**
     * {@inheritdoc}
     */
    public string $group = 'tag';

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $tenantId,
        public ?int $entityId = null,
        public bool $skipSynced = false,
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $tenant = Tenant::withoutEagerLoads()
            ->initialized()
            ->find($this->tenantId);

        if (!($tenant instanceof Tenant)) {
            return;
        }

        $tenant->run(function () {
            $webflow = Webflow::retrieve();

            if (!$webflow->is_activated) {
                return;
            }

            $collection = $webflow->config->collections['tag'] ?? null;

            if (!is_array($collection)) {
                return;
            }

            if (empty($collection['mappings'])) {
                return;
            }

            $query = Tag::withTrashed()->withoutEagerLoads();

            if ($this->entityId) {
                $query->where('id', '=', $this->entityId);
            }

            if ($this->skipSynced) {
                $query->whereNull('webflow_id');
            }

            foreach ($query->lazyById() as $tag) {
                $this->entityId = $tag->id;

                if ($tag->trashed()) {
                    if ($tag->webflow_id !== null) {
                        $this->trash($collection['id'], $tag->webflow_id);
                    }

                    continue;
                }

                $data = $this->toFieldData(
                    $tag,
                    $collection['fields'],
                    $collection['mappings'],
                );

                if (empty($data)) {
                    continue;
                }

                if (!$this->validate($data, $collection['fields'], $tag)) {
                    if ($this->skipSynced) {
                        throw new RuntimeException('Failed to sync content to Webflow.');
                    }
                }

                $item = $this->createOrUpdateItem(
                    $collection['id'],
                    $tag,
                    [
                        'isArchived' => false,
                        'isDraft' => false,
                        'fieldData' => $data,
                    ],
                    true,
                );

                if ($item === null) {
                    continue;
                }

                $tag->update(['webflow_id' => $item->id]);

                ingest(
                    data: [
                        'name' => 'webflow.tag.sync',
                        'source_type' => 'tag',
                        'source_id' => $this->entityId,
                        'webflow_id' => $item->id,
                    ],
                    type: 'action',
                );
            }
        });
    }
}
