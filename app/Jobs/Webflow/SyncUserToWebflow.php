<?php

declare(strict_types=1);

namespace App\Jobs\Webflow;

use App\Models\Tenant;
use App\Models\Tenants\Integrations\Webflow;
use App\Models\Tenants\User;
use Illuminate\Contracts\Database\Eloquent\Builder;
use RuntimeException;

class SyncUserToWebflow extends WebflowSyncJob
{
    /**
     * {@inheritdoc}
     */
    public string $group = 'author';

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

            $collection = $webflow->config->collections['author'] ?? null;

            if (!is_array($collection)) {
                return;
            }

            if (empty($collection['mappings'])) {
                return;
            }

            $query = User::withoutEagerLoads()
                ->with([
                    'parent',
                    'articles' => function (Builder $query) {
                        $query->withoutEagerLoads() // @phpstan-ignore-line
                            ->select(['id'])
                            ->published(true);
                    },
                ]);

            if ($this->entityId) {
                $query->where('id', '=', $this->entityId);
            }

            if ($this->skipSynced) {
                $query->whereNull('webflow_id');
            }

            foreach ($query->lazyById() as $user) {
                $this->entityId = $user->id;

                $data = $this->toFieldData(
                    $user,
                    $collection['fields'],
                    $collection['mappings'],
                );

                if (empty($data)) {
                    continue;
                }

                if (!$this->validate($data, $collection['fields'], $user)) {
                    if ($this->skipSynced) {
                        throw new RuntimeException('Failed to sync content to Webflow.');
                    }
                }

                // skip user who has not set a name.
                if (empty($data['slug']) || empty($data['name'])) {
                    continue;
                }

                $draft = $user->articles->isEmpty() &&
                    in_array($user->role, ['author', 'contributor'], true);

                if ($this->tenantId !== 'PEF3IPQHI') {
                    $draft = false;
                }

                if ($user->webflow_id !== null && array_key_exists('slug', $data)) {
                    unset($data['slug']);
                }

                $item = $this->createOrUpdateItem(
                    $collection['id'],
                    $user,
                    [ // @phpstan-ignore-line
                        'isArchived' => false,
                        'isDraft' => $draft,
                        'fieldData' => $data,
                    ],
                    !$draft,
                );

                if ($item === null) {
                    continue;
                }

                $user->update(['webflow_id' => $item->id]);

                ingest(
                    data: [
                        'name' => 'webflow.user.sync',
                        'source_type' => 'user',
                        'source_id' => $this->entityId,
                        'webflow_id' => $item->id,
                    ],
                    type: 'action',
                );
            }
        });
    }
}
