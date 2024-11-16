<?php

namespace App\Jobs\WordPress;

use App\Console\Commands\Tenants\ReindexScout;
use App\Enums\CustomField\Type;
use App\Models\Media;
use App\Models\Tenant;
use App\Models\Tenants\Article;
use App\Models\Tenants\CustomField;
use App\Models\Tenants\CustomFieldGroup;
use App\Models\Tenants\CustomFieldValue;
use App\Models\Tenants\Desk;
use App\Models\Tenants\Integrations\WordPress;
use App\Models\Tenants\Stage;
use App\Models\Tenants\Tag;
use App\Models\Tenants\User;
use App\Observers\ArticleCorrelationObserver;
use App\Observers\TriggerSiteRebuildObserver;
use App\Observers\WebhookPushingObserver;
use Generator;
use GuzzleHttp\Exception\TooManyRedirectsException;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Storipress\WordPress\Exceptions\InvalidPostPageNumberException;
use Storipress\WordPress\Exceptions\WordPressException;
use Storipress\WordPress\Objects\Media as MediaObject;
use Storipress\WordPress\Objects\Post;
use Storipress\WordPress\Objects\PostRevision as PostRevisionObject;
use Throwable;

use function Sentry\captureException;

class PullPostsFromWordPress extends WordPressJob
{
    /**
     * @var array<string, array{
     *     id: int,
     *     type: string,
     *     target: string|null,
     * }>
     */
    public array $acfFields = [];

    protected ?int $acfGroupId;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $tenantId,
        public ?int $wordpressId = null,
    ) {
        //
    }

    /**
     * {@inheritdoc}
     */
    public function overlappingKey(): string
    {
        return sprintf(
            '%s:%s',
            $this->tenantId,
            $this->wordpressId ?: 'all',
        );
    }

    /**
     * Handle the given event.
     */
    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $tenant = Tenant::withoutEagerLoads()
            ->initialized()
            ->find($this->tenantId);

        if (! ($tenant instanceof Tenant)) {
            return;
        }

        TriggerSiteRebuildObserver::mute();

        WebhookPushingObserver::mute();

        ArticleCorrelationObserver::mute();

        $tenant->run(function (Tenant $tenant) {
            $wordpress = WordPress::retrieve();

            if (! $wordpress->is_activated) {
                return;
            }

            Article::disableSearchSyncing();

            $prosemirror = app('prosemirror');

            $desks = Desk::withoutEagerLoads()
                ->whereNotNull('wordpress_id')
                ->pluck('id', 'wordpress_id')
                ->toArray();

            $tags = Tag::withoutEagerLoads()
                ->whereNotNull('wordpress_id')
                ->pluck('id', 'wordpress_id')
                ->toArray();

            $users = User::withoutEagerLoads()
                ->whereNotNull('wordpress_id')
                ->pluck('id', 'wordpress_id')
                ->toArray();

            $draft = Stage::default()->value('id');

            $ready = Stage::ready()->value('id');

            $defaultDeskId = Desk::withTrashed()
                ->withoutEagerLoads()
                ->firstOrCreate(['desk_id' => null, 'name' => 'Uncategorized'])
                ->id;

            $this->acfGroupId = CustomFieldGroup::withTrashed()
                ->withoutEagerLoads()
                ->where('key', '=', 'acf')
                ->first(['id'])
                ?->id;

            $emptyDoc = [
                'type' => 'doc',
                'content' => [],
            ];

            $now = now();

            $error = 0;

            foreach ($this->posts() as $post) {
                try {
                    $title = html_entity_decode(trim($post->title->rendered) ?: 'Untitled');

                    $blurb = html_entity_decode(trim($post->excerpt->rendered)) ?: null;

                    $origin = html_entity_decode(trim($post->content->rendered));

                    if (strlen($origin) < 165) {
                        foreach ($this->revisions($post->id) as $revision) {
                            if (strlen($revision->content->rendered) > 165) {
                                $origin = html_entity_decode(trim($revision->content->rendered));
                            }
                        }
                    }

                    $content = $prosemirror->toProseMirror($origin);

                    $published = $post->status === 'publish';

                    $attributes = [
                        'wordpress_id' => $post->id,
                        'desk_id' => $desks[Arr::first($post->categories)] ?? $defaultDeskId,
                        'stage_id' => $published ? $ready : $draft,
                        'title' => $title,
                        'slug' => $post->slug,
                        'blurb' => $blurb,
                        'featured' => $post->sticky,
                        'document' => [
                            'default' => $content,
                            'title' => $prosemirror->toProseMirror($title) ?: $emptyDoc,
                            'blurb' => empty($blurb) ? $emptyDoc : ($prosemirror->toProseMirror($blurb) ?: $emptyDoc),
                            'annotations' => [],
                        ],
                        'plaintext' => $prosemirror->toPlainText($content ?: []),
                        'encryption_key' => base64_encode(random_bytes(32)),
                        'published_at' => $published ? $post->modified_gmt : null,
                        'created_at' => $post->date_gmt,
                        'updated_at' => $post->modified_gmt,
                        'deleted_at' => $post->status === 'trash' ? $now : null,
                    ];

                    if (isset($post->yoast_head_json) && is_object($post->yoast_head_json)) {
                        $attributes['seo'] = [
                            'og' => [
                                'title' => $post->yoast_head_json->og_title ?? '',
                                'description' => $post->yoast_head_json->og_description ?? '',
                            ],
                            'meta' => [
                                'title' => $post->yoast_head_json->title ?? '',
                                'description' => $post->yoast_head_json->description ?? '',
                            ],
                            'hasSlug' => false,
                            'ogImage' => $post->yoast_head_json->og_image[0]->url ?? null,
                        ];
                    }

                    $article = Article::withTrashed()
                        ->withoutEagerLoads()
                        ->where(function (Builder $query) use ($post) {
                            $query->where('wordpress_id', '=', $post->id)
                                ->orWhere('slug', '=', $post->slug);
                        })
                        ->get()
                        ->map(function (Article $article, int $idx) use ($now) {
                            // Due to unknown reasons, the same article might have multiple
                            // records on Storipress. So, we'll delete the extra ones.
                            if ($idx > 0) {
                                $article->update([
                                    'wordpress_id' => null,
                                    'slug' => sprintf('%s-%d', $article->slug, $now->timestamp),
                                    'deleted_at' => $now,
                                ]);
                            }

                            return $article;
                        })
                        ->first();

                    if ($article instanceof Article) {
                        $article->update($attributes);
                    } else {
                        $article = Article::create($attributes);
                    }

                    $article->timestamps = false;

                    $article->update([
                        'html' => $prosemirror->toHTML($content, [
                            'client_id' => $this->tenantId,
                            'article_id' => $article->id,
                        ]),
                    ]);

                    // If this article includes a Feature Image, download the image to Storipress.
                    // This way, we can avoid issues like HotLink protection.
                    if (($media = $this->media($post->featured_media)) instanceof MediaObject) {
                        $url = $this->fetch($media->source_url, $article->id);

                        $caption = html_entity_decode(trim($media->caption->rendered));

                        $article->update([
                            'cover' => [
                                'alt' => $media->alt_text,
                                'caption' => $caption,
                                'url' => $url,
                                'wordpress' => [
                                    'id' => $media->id,
                                    'alt' => $media->alt_text,
                                    'caption' => $caption,
                                    'url' => $url,
                                ],
                            ],
                        ]);
                    }

                    if (! empty($post->tags)) {
                        // transform WordPress tags to Storipress tag id,
                        // and remove non-existing ones.
                        $ids = array_filter(
                            array_map(
                                fn ($id) => $tags[$id] ?? 0,
                                $post->tags,
                            ),
                        );

                        // assign the tags to this article
                        if (! empty($ids)) {
                            $article->tags()->syncWithoutDetaching($ids);
                        }
                    }

                    if (isset($users[$post->author])) {
                        $article->authors()->syncWithoutDetaching(
                            [$users[$post->author]],
                        );
                    }

                    if ($this->acfGroupId !== null && isset($post->acf) && is_object($post->acf)) {
                        $this->acf($article->id, (array) $post->acf);
                    }

                    ingest(
                        data: [
                            'name' => 'wordpress.article.pull',
                            'source_type' => 'article',
                            'source_id' => $article->id,
                            'wordpress_id' => $post->id,
                        ],
                        type: 'action',
                    );
                } catch (Throwable $e) {
                    captureException($e);

                    if ((++$error) === 5) {
                        break;
                    }
                }
            }

            Article::enableSearchSyncing();
        });

        Artisan::call(ReindexScout::class, ['tenant' => $this->tenantId]);

        ArticleCorrelationObserver::unmute();

        WebhookPushingObserver::unmute();

        TriggerSiteRebuildObserver::unmute();
    }

    /**
     * 取得所有 posts。
     *
     * @return Generator<int, Post>
     */
    public function posts(): Generator
    {
        $api = app('wordpress')->post();

        $arguments = [
            'page' => 1,
            'per_page' => 25,
            'orderby' => 'id',
            'order' => 'asc',
            'status' => 'any',
        ];

        if (is_int($this->wordpressId)) {
            $arguments['include'] = [$this->wordpressId];
        }

        do {
            try {
                $posts = $api->list($arguments);
            } catch (InvalidPostPageNumberException) {
                break;
            }

            foreach ($posts as $post) {
                yield $post;
            }

            $arguments['page']++;
        } while (count($posts) === $arguments['per_page']);
    }

    /**
     * 取得文章 revisions。
     *
     * @return array<int, PostRevisionObject>
     */
    public function revisions(int $id): array
    {
        return app('wordpress')->postRevision()->list($id, [
            'page' => 1,
            'per_page' => 25,
        ]);
    }

    /**
     * 透過 id 取得指定附件。
     */
    public function media(int $id): ?MediaObject
    {
        if ($id === 0) {
            return null;
        }

        try {
            return app('wordpress')->media()->retrieve($id);
        } catch (WordPressException) {
            return null;
        }
    }

    /**
     * Fetch external resource.
     */
    protected function fetch(string $url, int $articleId): string
    {
        try {
            $temp = temp_file();

            app('http2')
                ->connectTimeout(7)
                ->timeout(15)
                ->withoutVerifying()
                ->accept('*/*')
                ->withOptions(['sink' => $temp])
                ->get($url)
                ->throw();

            $mime = mime_content_type($temp);

            if (empty($mime) || ! Str::startsWith($mime, 'image/')) {
                return $url;
            }

            $dimensions = getimagesize($temp);

            if ($dimensions !== false) {
                [$width, $height] = $dimensions;
            }

            $name = Str::afterLast($url, '/');

            $media = Media::create([
                'token' => unique_token(),
                'tenant_id' => $this->tenantId,
                'model_type' => Article::class,
                'model_id' => $articleId,
                'collection' => 'hero-photo',
                'path' => $this->upload(new UploadedFile($temp, $name)),
                'mime' => $mime,
                'size' => filesize($temp),
                'width' => $width ?? 0,
                'height' => $height ?? 0,
                'blurhash' => null,
            ]);

            return $media->url;
        } catch (TooManyRedirectsException|RequestException|ConnectionException) {
            // ignored
        } catch (Throwable $e) {
            captureException($e);
        }

        return $url;
    }

    /**
     * Upload file to AWS S3.
     */
    protected function upload(UploadedFile $file): string
    {
        $path = sprintf(
            'assets/media/images/%s.%s',
            unique_token(),
            $file->extension(),
        );

        Storage::drive('s3')->putFileAs(dirname($path), $file, basename($path));

        return $path;
    }

    /**
     * @param  array<string, string|bool|null|int|array<int, int>>  $data
     */
    protected function acf(int $articleId, array $data): void
    {
        // Exclude keys that have already been queried and have data.
        $includes = array_filter(
            array_filter(array_keys($data)),
            fn ($include) => ! isset($this->acfFields[$include]),
        );

        if (! empty($includes)) {
            $fields = CustomField::withoutEagerLoads()
                ->where('custom_field_group_id', '=', $this->acfGroupId)
                ->whereIn('key', $includes)
                ->get();

            foreach ($fields as $field) {
                $this->acfFields[$field->key] = [
                    'id' => $field->id,
                    'type' => $field->type,
                    'target' => $field->options['target'] ?? null,
                ];
            }
        }

        foreach ($data as $key => $value) {
            if (! isset($this->acfFields[$key])) {
                continue;
            }

            $field = $this->acfFields[$key];

            if (Type::reference()->is($field['type'])) {
                if (! is_array($value)) {
                    continue;
                }

                $value = match ($field['target']) {
                    Tag::class => Tag::withTrashed()
                        ->whereIn('wordpress_id', array_values($value))
                        ->pluck('id')
                        ->toArray(),
                    Desk::class => Desk::withTrashed()
                        ->whereIn('wordpress_id', array_values($value))
                        ->pluck('id')
                        ->toArray(),
                    default => null,
                };

                if (empty($value)) {
                    continue;
                }
            }

            CustomFieldValue::withTrashed()
                ->withoutEagerLoads()
                ->updateOrCreate([
                    'custom_field_id' => $field['id'],
                    'custom_field_morph_id' => $articleId,
                    'custom_field_morph_type' => Article::class,
                ], [
                    'type' => $field['type'],
                    'value' => $value,
                    'deleted_at' => null,
                ]);
        }
    }
}
