<?php

namespace App\Jobs\WordPress;

use App\Enums\CustomField\Type;
use App\Models\Tenant;
use App\Models\Tenants\Article;
use App\Models\Tenants\CustomFieldGroup;
use App\Models\Tenants\Integrations\WordPress;
use App\UploadedFileHelper;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\UploadedFile;
use Storipress\WordPress\Exceptions\WordPressException;
use Storipress\WordPress\Objects\Media;

class SyncArticleAcfToWordPress extends WordPressJob
{
    use UploadedFileHelper;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $tenantId,
        public int $articleId,
    ) {
        //
    }

    /**
     * {@inheritdoc}
     */
    public function overlappingKey(): string
    {
        return sprintf('%s:%d', $this->tenantId, $this->articleId);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $tenant = Tenant::withoutEagerLoads()
            ->initialized()
            ->find($this->tenantId);

        if (!($tenant instanceof Tenant)) {
            return;
        }

        $tenant->run(function () {
            $wordpress = WordPress::retrieve();

            if (!$wordpress->is_activated) {
                return;
            }

            if (version_compare($wordpress->config->version, '0.0.14', '<')) {
                return;
            }

            if (!$wordpress->config->feature['acf'] && !$wordpress->config->feature['acf_pro']) {
                return;
            }

            $article = Article::withTrashed()
                ->withoutEagerLoads()
                ->find($this->articleId);

            if (!($article instanceof Article)) {
                return;
            }

            if ($article->wordpress_id === null) {
                return;
            }

            $group = CustomFieldGroup::withTrashed()
                ->withoutEagerLoads()
                ->where('key', '=', 'acf')
                ->with(['customFields.values' => function (HasMany $query) use ($article) {
                    $query->where('custom_field_morph_id', '=', $article->id)
                        ->where('custom_field_morph_type', '=', Article::class);
                }])
                ->first();

            if (!($group instanceof CustomFieldGroup)) {
                return;
            }

            $acf = [];

            foreach ($group->customFields as $field) {
                foreach ($field->values as $customFieldValue) {
                    if (Type::file()->is($field->type)) {
                        if (empty($customFieldValue->value) || !is_array($customFieldValue->value)) {
                            $acf[$field->key] = null;
                        } elseif (data_get($customFieldValue->value, 'wordpress_id')) {
                            continue;
                        } else {
                            $url = data_get($customFieldValue->value, 'url');

                            if (!is_not_empty_string($url)) {
                                continue;
                            }

                            if (!($file = $this->toUploadedFile($url))) {
                                continue;
                            }

                            if (!($media = $this->createOrUpdateMedia(null, $file, []))) {
                                continue;
                            }

                            $value = $customFieldValue->value;

                            $value['wordpress_id'] = $media->id;

                            $customFieldValue->update([
                                'value' => $value,
                            ]);

                            $acf[$field->key] = $media->id;
                        }
                    } elseif (Type::reference()->is($field->type)) {
                        $value = $customFieldValue->value;

                        if (!($value instanceof Collection)) {
                            continue;
                        }

                        $ids = $value->whereNotNull('wordpress_id')
                            ->pluck('wordpress_id')
                            ->toArray();

                        $acf[$field->key] = empty($ids) ? null : $ids;
                    } else {
                        $acf[$field->key] = $customFieldValue->value;
                    }
                }
            }

            if (empty($acf)) {
                return;
            }

            app('wordpress')->post()->update($article->wordpress_id, [
                'acf' => $acf,
            ]);

            ingest(
                data: [
                    'name' => 'wordpress.article.acf.sync',
                    'source_type' => 'article',
                    'source_id' => $this->articleId,
                    'wordpress_id' => $article->wordpress_id,
                ],
                type: 'action',
            );
        });
    }

    /**
     * @param  array<string, mixed>  $params
     *
     * @throws WordPressException
     */
    public function createOrUpdateMedia(?int $id, ?UploadedFile $file, array $params): ?Media
    {
        $api = app('wordpress')->media();

        if (is_int($id)) {
            return $api->update($id, $params);
        }

        if (!$file) {
            return null;
        }

        return $api->create($file, $params);
    }
}
