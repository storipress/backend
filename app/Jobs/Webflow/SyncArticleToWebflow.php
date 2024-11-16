<?php

declare(strict_types=1);

namespace App\Jobs\Webflow;

use App\Enums\Webflow\FieldType;
use App\Models\Tenant;
use App\Models\Tenants\Article;
use App\Models\Tenants\Integrations\Configurations\WebflowConfiguration;
use App\Models\Tenants\Integrations\Webflow;
use App\Sluggable;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use RuntimeException;
use Storipress\Webflow\Exceptions\Exception as WebflowException;

/**
 * @phpstan-import-type WebflowCollectionFields from WebflowConfiguration
 */
class SyncArticleToWebflow extends WebflowSyncJob
{
    /**
     * {@inheritdoc}
     */
    public string $group = 'article';

    /**
     * @var array<string, non-empty-string|null>
     */
    public array $firstItemIds = [];

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

        if (! ($tenant instanceof Tenant)) {
            return;
        }

        $tenant->run(function () {
            $webflow = Webflow::retrieve();

            if (! $webflow->is_activated) {
                return;
            }

            $collection = $webflow->config->collections['blog'] ?? null;

            if (! is_array($collection)) {
                return;
            }

            if (empty($collection['mappings'])) {
                return;
            }

            $query = Article::withTrashed()
                ->withoutEagerLoads()
                ->with([
                    'stage',
                    'authors' => function (Builder $query) {
                        $query->withoutEagerLoads()
                            ->whereNotNull('webflow_id')
                            ->select(['id', 'webflow_id']);
                    },
                    'desk' => function (Builder $query) {
                        $query->withoutEagerLoads()
                            ->whereNotNull('webflow_id')
                            ->select(['id', 'webflow_id']);
                    },
                    'tags' => function (Builder $query) {
                        $query->withoutEagerLoads()
                            ->whereNotNull('webflow_id')
                            ->select(['id', 'webflow_id']);
                    },
                ]);

            if ($this->entityId) {
                $query->where('id', '=', $this->entityId);
            }

            if ($this->skipSynced) {
                $query->whereNull('webflow_id');
            }

            foreach ($query->lazyById() as $article) {
                $this->entityId = $article->id;

                if ($article->trashed()) {
                    if ($article->webflow_id !== null) {
                        $this->trash($collection['id'], $article->webflow_id);
                    }

                    continue;
                }

                if ($webflow->config->sync_when === 'published') {
                    if (! $article->published) {
                        continue;
                    }
                } elseif ($webflow->config->sync_when === 'ready') {
                    if (! $article->stage->ready) {
                        continue;
                    }
                }

                $data = $this->toFieldData(
                    $article,
                    $collection['fields'],
                    $collection['mappings'],
                );

                if ($article->webflow_id === null) {
                    $data = $this->fillRequiredFields($data, $collection['fields']);
                }

                if (empty($data)) {
                    continue;
                }

                if (! $this->validate($data, $collection['fields'], $article)) {
                    if ($this->skipSynced) {
                        throw new RuntimeException('Failed to sync content to Webflow.');
                    }
                }

                if (isset($data['slug']) && is_string($data['slug'])) {
                    $data['slug'] = Sluggable::slug($data['slug']);
                }

                if ($this->tenantId === 'PEF3IPQHI') { // IDEO
                    foreach ($article->authors as $author) {
                        SyncUserToWebflow::dispatchSync(
                            $this->tenantId,
                            $author->id,
                        );
                    }
                }

                $params = [
                    'isArchived' => false,
                    'isDraft' => ! $article->published,
                    'fieldData' => $data,
                ];

                $item = $this->createOrUpdateItem(
                    $collection['id'],
                    $article,
                    $params,
                    true,
                );

                if ($item === null) {
                    continue;
                }

                $article->update([
                    'slug' => $data['slug'],
                    'webflow_id' => $item->id,
                ]);

                ingest(
                    data: [
                        'name' => 'webflow.article.sync',
                        'source_type' => 'article',
                        'source_id' => $this->entityId,
                        'webflow_id' => $item->id,
                    ],
                    type: 'action',
                );
            }
        });
    }

    /**
     * @param  array<non-empty-string, mixed>  $data
     * @param  WebflowCollectionFields  $fields
     * @return array<non-empty-string, mixed>
     */
    public function fillRequiredFields(array $data, array $fields): array
    {
        foreach ($fields as $field) {
            if (! $field['isRequired']) {
                continue;
            }

            [
                'slug' => $key,
                'type' => $type,
            ] = $field;

            if (isset($data[$key]) && ! $this->isEmpty($data[$key])) {
                continue;
            }

            $value = match ($type) {
                FieldType::plainText,
                FieldType::richText => ' ',
                FieldType::file,
                FieldType::image => 'https://storipress.com/images/horizontal.svg',
                FieldType::multiImage => ['https://storipress.com/images/horizontal.svg'],
                FieldType::videoLink,
                FieldType::link => 'https://storipress.com',
                FieldType::email => 'support@storipress.com',
                FieldType::number => 0,
                FieldType::dateTime => '1970-01-01T00:00:00+00:00',
                FieldType::switch => false,
                FieldType::color => '#FFFFFF',
                FieldType::option => data_get($field, 'validations.options.0.id'),
                FieldType::reference,
                FieldType::multiReference => call_user_func(
                    function () use ($field, $type) {
                        $collectionId = data_get($field, 'validations.collectionId');

                        if (! is_not_empty_string($collectionId)) {
                            return null;
                        }

                        $value = $this->getFirstItemId($collectionId);

                        if ($value === null) {
                            return null;
                        }

                        if ($type === FieldType::multiReference) {
                            return Arr::wrap($value);
                        }

                        return $value;
                    },
                ),
                default => null,
            };

            if ($value === null) {
                continue;
            }

            $data[$key] = $value;
        }

        return $data;
    }

    /**
     * @return non-empty-string|null
     */
    public function getFirstItemId(string $collectionId): ?string
    {
        if (array_key_exists($collectionId, $this->firstItemIds)) {
            return $this->firstItemIds[$collectionId];
        }

        try {
            $items = app('webflow')->item()->list($collectionId, 0, 1);
        } catch (WebflowException) {
            return $this->firstItemIds[$collectionId] = null;
        }

        $data = $items['data'];

        return $this->firstItemIds[$collectionId] = ! empty($data) ? $data[0]->id : null;
    }
}
