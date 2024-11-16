<?php

declare(strict_types=1);

namespace App\Jobs\Webflow;

use App\Models\Tenant;
use App\Models\Tenants\Desk;
use App\Models\Tenants\Integrations\Webflow;
use Illuminate\Contracts\Database\Eloquent\Builder;
use RuntimeException;

class SyncDeskToWebflow extends WebflowSyncJob
{
    /**
     * {@inheritdoc}
     */
    public string $group = 'desk';

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

            $collection = $webflow->config->collections['desk'] ?? null;

            if (!is_array($collection)) {
                return;
            }

            if (empty($collection['mappings'])) {
                return;
            }

            $query = Desk::withTrashed()
                ->withoutEagerLoads()
                ->with([
                    'editors' => function (Builder $query) {
                        $query->withoutEagerLoads()
                            ->whereNotNull('webflow_id');
                    },
                    'writers' => function (Builder $query) {
                        $query->withoutEagerLoads()
                            ->whereNotNull('webflow_id')
                            ->whereHas('articles', function (Builder $query) {
                                $query->published(true); // @phpstan-ignore-line
                            });
                    },
                ]);

            if ($this->entityId) {
                $query->where('id', '=', $this->entityId);
            }

            if ($this->skipSynced) {
                $query->whereNull('webflow_id');
            }

            foreach ($query->lazyById() as $desk) {
                $this->entityId = $desk->id;

                if ($desk->trashed()) {
                    if ($desk->webflow_id !== null) {
                        $this->trash($collection['id'], $desk->webflow_id);
                    }

                    continue;
                }

                $data = $this->toFieldData(
                    $desk,
                    $collection['fields'],
                    $collection['mappings'],
                );

                if (empty($data)) {
                    continue;
                }

                if (!$this->validate($data, $collection['fields'], $desk)) {
                    if ($this->skipSynced) {
                        throw new RuntimeException('Failed to sync content to Webflow.');
                    }
                }

                $item = $this->createOrUpdateItem(
                    $collection['id'],
                    $desk,
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

                $desk->update(['webflow_id' => $item->id]);

                ingest(
                    data: [
                        'name' => 'webflow.desk.sync',
                        'source_type' => 'desk',
                        'source_id' => $this->entityId,
                        'webflow_id' => $item->id,
                    ],
                    type: 'action',
                );
            }
        });
    }
}
