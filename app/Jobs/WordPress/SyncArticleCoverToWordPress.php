<?php

namespace App\Jobs\WordPress;

use App\Models\Tenant;
use App\Models\Tenants\Article;
use App\Models\Tenants\Integrations\WordPress;
use App\UploadedFileHelper;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Storipress\WordPress\Exceptions\InvalidPostIdException;
use Storipress\WordPress\Exceptions\WordPressException;
use Storipress\WordPress\Objects\Media;

class SyncArticleCoverToWordPress extends WordPressJob
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

            $article = Article::withTrashed()
                ->withoutEagerLoads()
                ->find($this->articleId);

            if (!($article instanceof Article)) {
                return;
            }

            if ($article->wordpress_id === null) {
                return;
            }

            $cover = $article->cover;

            if ($cover === null) {
                return;
            }

            $caption = strip_tags(trim($cover['caption'] ?? ''));

            if (isset($cover['wordpress'])) {
                if (
                    $cover['alt'] === $cover['wordpress']['alt'] &&
                    $this->cleanup($caption) === $this->cleanup($cover['wordpress']['caption']) &&
                    $cover['url'] === $cover['wordpress']['url']
                ) {
                    return; // 沒有資料變更，直接略過
                }

                if ($cover['url'] === $cover['wordpress']['url']) {
                    // 僅 alt 或 caption 變更
                    $media = $this->createOrUpdateMedia($cover['wordpress']['id'], null, [
                        'alt_text' => $cover['alt'],
                        'caption' => $caption,
                    ]);

                    if ($media instanceof Media) {
                        $cover['wordpress']['alt'] = $cover['alt'];

                        $cover['wordpress']['caption'] = $caption;

                        $article->update(['cover' => $cover]);

                        ingest(
                            data: [
                                'name' => 'wordpress.article.cover.sync',
                                'source_type' => 'article',
                                'source_id' => $this->articleId,
                                'wordpress_id' => $cover['wordpress']['id'],
                            ],
                            type: 'action',
                        );

                        return;
                    }
                }
            }

            if (empty($cover['url'])) {
                return;
            }

            $file = $this->toUploadedFile($cover['url']);

            if ($file === false) {
                return;
            }

            $media = $this->createOrUpdateMedia(null, $file, [
                'alt_text' => $cover['alt'],
                'caption' => $caption,
            ]);

            if ($media === null) {
                return;
            }

            $cover['wordpress'] = [
                'id' => $media->id,
                'url' => $cover['url'],
                'alt' => $cover['alt'],
                'caption' => $caption,
            ];

            $article->update(['cover' => $cover]);

            app('wordpress')->post()->update($article->wordpress_id, [
                'featured_media' => $media->id,
            ]);

            ingest(
                data: [
                    'name' => 'wordpress.article.cover.sync',
                    'source_type' => 'article',
                    'source_id' => $this->articleId,
                    'wordpress_id' => $media->id,
                ],
                type: 'action',
            );
        });
    }

    /**
     * 移除 HTML 標籤以及 whitespace。
     */
    public function cleanup(string $value): string
    {
        return Str::of($value)
            ->stripTags()
            ->replaceMatches('/\s/', '')
            ->value();
    }

    /**
     * @param  array<string, mixed>  $params
     *
     * @throws WordPressException
     */
    public function createOrUpdateMedia(
        ?int $id,
        ?UploadedFile $file,
        array $params,
    ): ?Media {
        $api = app('wordpress')->media();

        if (is_int($id)) {
            try {
                return $api->update($id, $params);
            } catch (InvalidPostIdException) {
                return null;
            }
        }

        if (!($file instanceof UploadedFile)) {
            return null;
        }

        return $api->create($file, $params);
    }
}
