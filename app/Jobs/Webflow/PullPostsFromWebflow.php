<?php

declare(strict_types=1);

namespace App\Jobs\Webflow;

use App\Console\Commands\Tenants\ReindexScout;
use App\Enums\CustomField\Type;
use App\Models\Tenant;
use App\Models\Tenants\Article;
use App\Models\Tenants\CustomField;
use App\Models\Tenants\Desk;
use App\Models\Tenants\Integrations\Webflow;
use App\Models\Tenants\Stage;
use App\Models\Tenants\Tag;
use App\Models\Tenants\User;
use App\Observers\ArticleCorrelationObserver;
use App\Observers\TriggerSiteRebuildObserver;
use App\Observers\WebhookPushingObserver;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

class PullPostsFromWebflow extends WebflowPullJob
{
    /**
     * {@inheritdoc}
     */
    public string $rateLimiterName = 'webflow-api-unlimited';

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $tenantId,
        public ?string $webflowId = null,
    ) {
        //
    }

    /**
     * {@inheritdoc}
     */
    public function rateLimitingKey(): string
    {
        return '';
    }

    /**
     * {@inheritdoc}
     */
    public function overlappingKey(): string
    {
        return sprintf(
            '%s:%s',
            $this->tenantId,
            $this->webflowId ?: 'all',
        );
    }

    /**
     * {@inheritdoc}
     */
    public function throttlingKey(): string
    {
        return sprintf('webflow:%s:unlimited', $this->tenantId);
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

        TriggerSiteRebuildObserver::mute();

        WebhookPushingObserver::mute();

        ArticleCorrelationObserver::mute();

        $tenant->run(function () {
            $webflow = Webflow::retrieve();

            if (!$webflow->is_activated) {
                return;
            }

            $collection = $webflow->config->collections['blog'] ?? null;

            if (!is_array($collection)) {
                return;
            }

            if (empty($collection['mappings'])) {
                return;
            }

            Article::disableSearchSyncing();

            $ecHp = $this->ecHp();

            $prosemirror = app('prosemirror');

            $desks = Desk::withTrashed()
                ->withoutEagerLoads()
                ->whereNotNull('webflow_id')
                ->pluck('id', 'webflow_id')
                ->toArray();

            $tags = Tag::withTrashed()
                ->withoutEagerLoads()
                ->whereNotNull('webflow_id')
                ->pluck('id', 'webflow_id')
                ->toArray();

            $users = User::withoutEagerLoads()
                ->whereNotNull('webflow_id')
                ->pluck('id', 'webflow_id')
                ->toArray();

            $draft = Stage::default()->value('id');

            $ready = Stage::ready()->value('id');

            $defaultDeskId = Desk::withTrashed()
                ->withoutEagerLoads()
                ->firstOrCreate(['desk_id' => null, 'name' => 'Uncategorized'])
                ->id;

            $mapping = $this->mapping($collection);

            foreach ($this->items($collection['id']) as $item) {
                $extra = [];

                $attributes = [
                    'webflow_id' => $item->id,
                    'desk_id' => $defaultDeskId,
                    'stage_id' => $item->isDraft || $item->isArchived ? $draft : $ready,
                    'title' => 'Untitled',
                    'encryption_key' => base64_encode(random_bytes(32)),
                    'published_at' => $item->lastPublished,
                    'created_at' => $item->createdOn,
                    'updated_at' => $item->lastUpdated,
                    'deleted_at' => null,
                ];

                foreach (get_object_vars($item->fieldData) as $slug => $value) {
                    if (!isset($mapping[$slug])) {
                        continue;
                    }

                    if ($value === null) {
                        continue;
                    }

                    $key = $mapping[$slug];

                    if ($key === 'desk') {
                        $attributes['desk_id'] = $desks[$value] ?? $defaultDeskId;
                    } elseif (Str::startsWith($key, 'custom_fields.')) {
                        $extra[$key] = $value;
                    } elseif ($key === 'cover.url') {
                        $attributes['cover'] = [
                            'alt' => '',
                            'caption' => '',
                            'url' => $value->url, // @phpstan-ignore-line
                        ];
                    } else {
                        Arr::set($attributes, $key, $value);
                    }
                }

                $attributes['document'] = [
                    'default' => $prosemirror->toProseMirror($attributes['html'] ?? ''),
                    'title' => $prosemirror->toProseMirror($attributes['title'] ?? ''),
                    'blurb' => $prosemirror->toProseMirror($attributes['blurb'] ?? ''),
                    'annotations' => [],
                ];

                $attributes['plaintext'] = $prosemirror->toPlainText($attributes['document']['default']);

                $article = Article::withTrashed()
                    ->withoutEagerLoads()
                    ->where(function (Builder $query) use ($item, $attributes) {
                        $query->where('webflow_id', '=', $item->id)
                            ->orWhere('slug', '=', $attributes['slug']);
                    })
                    ->updateOrCreate([], Arr::except($attributes, ['authors', 'tags']))
                    ->refresh();

                $article->timestamps = false;

                $article->update([
                    'html' => $prosemirror->toHTML($attributes['document']['default'], [
                        'client_id' => $this->tenantId,
                        'article_id' => $article->id,
                    ]),
                ]);

                if (isset($attributes['authors'])) {
                    $ids = array_filter(
                        array_map(
                            fn ($id) => $users[$id] ?? 0,
                            Arr::wrap($attributes['authors']),
                        ),
                    );

                    if (!empty($ids)) {
                        $article->authors()->sync($ids);
                    }
                }

                if (isset($attributes['tags'])) {
                    $ids = array_filter(
                        array_map(
                            fn ($id) => $tags[$id] ?? 0,
                            Arr::wrap($attributes['tags']),
                        ),
                    );

                    if (!empty($ids)) {
                        $article->tags()->sync($ids);
                    }
                }

                foreach ($extra as $key => $values) {
                    $custom = data_get($article, $key);

                    if (!($custom instanceof CustomField)) {
                        continue;
                    }

                    if (Type::reference()->is($custom->type)) {
                        $custom->values()->firstOrCreate([
                            'custom_field_morph_id' => $article->id,
                            'custom_field_morph_type' => get_class($article),
                            'type' => $custom->type,
                            'value' => Arr::wrap($values),
                        ]);

                        continue;
                    }

                    foreach (Arr::wrap($values) as $value) {
                        if (isset($value->url)) {
                            $value = $value->url;
                        }

                        if (Type::file()->is($custom->type)) {
                            $value = $this->toFile($value);
                        } elseif (Type::select()->is($custom->type)) {
                            $value = Arr::wrap($value);
                        }

                        $custom->values()->firstOrCreate([
                            'custom_field_morph_id' => $article->id,
                            'custom_field_morph_type' => get_class($article),
                            'type' => $custom->type,
                            'value' => $value,
                        ]);
                    }
                }

                if ($ecHp) {
                    app('http2')->post($ecHp, [
                        'client_id' => $this->tenantId,
                        'article_id' => (string) $article->id,
                        'document' => $attributes['document'],
                    ]);
                }

                ingest(
                    data: [
                        'name' => 'webflow.article.pull',
                        'source_type' => 'article',
                        'source_id' => $article->id,
                        'webflow_id' => $item->id,
                    ],
                    type: 'action',
                );
            }

            Article::enableSearchSyncing();
        });

        Artisan::call(ReindexScout::class, ['tenant' => $this->tenantId]);

        ArticleCorrelationObserver::unmute();

        WebhookPushingObserver::unmute();

        TriggerSiteRebuildObserver::unmute();
    }

    public function ecHp(): ?string
    {
        if (app()->isProduction()) {
            return 'https://ec-hp.stori.press/api/replace-cache';
        }

        if (app()->environment('staging')) {
            return 'https://ec-hp.storipress.pro/api/replace-cache';
        }

        if (app()->environment('development')) {
            return 'https://ec-hp.storipress.dev/api/replace-cache';
        }

        return null;
    }
}
