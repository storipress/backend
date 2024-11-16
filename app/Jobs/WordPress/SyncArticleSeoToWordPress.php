<?php

namespace App\Jobs\WordPress;

use App\Models\Tenant;
use App\Models\Tenants\Article;
use App\Models\Tenants\Integrations\WordPress;
use App\UploadedFileHelper;
use Illuminate\Support\Str;
use Storipress\WordPress\Exceptions\NoRouteException;
use Storipress\WordPress\Exceptions\WordPressException;
use Throwable;

use function Sentry\captureException;

class SyncArticleSeoToWordPress extends WordPressJob
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

        if (! ($tenant instanceof Tenant)) {
            return;
        }

        $tenant->run(function () {
            $wordpress = WordPress::retrieve();

            if (! $wordpress->is_activated) {
                return;
            }

            if (version_compare($wordpress->config->version, '0.0.14', '<')) {
                return;
            }

            if (! $wordpress->config->feature['yoast_seo']) {
                return;
            }

            $article = Article::withTrashed()
                ->withoutEagerLoads()
                ->find($this->articleId);

            if (! ($article instanceof Article)) {
                return;
            }

            if (! $article->wordpress_id) {
                return;
            }

            $seo = $article->seo ?: [];

            $ogImage = data_get($seo, 'ogImage');

            $ogImageWpId = data_get($seo, 'ogImage_wordpress_id', -1);

            // upload og image to WordPress media.
            if (is_not_empty_string($ogImage) && $ogImageWpId === null) {
                if ($file = $this->toUploadedFile($ogImage)) {
                    try {
                        $media = app('wordpress')->media()->create($file, []);

                        $seo['ogImage_wordpress_id'] = $ogImageWpId = $media->id;

                        $article->update(['seo' => $seo]);
                    } catch (WordPressException) {
                        // ignored
                    }
                }
            }

            $options = [
                'seo_title' => data_get($seo, 'meta.title', ''),
                'seo_description' => data_get($seo, 'meta.description', ''),
                'og_title' => data_get($seo, 'og.title', ''),
                'og_description' => data_get($seo, 'og.description', ''),
                'og_image_id' => $ogImageWpId,
            ];

            try {
                app('wordpress')
                    ->request()
                    ->post('/storipress/update-yoast-seo-metadata', [
                        'id' => $article->wordpress_id,
                        'options' => $options,
                    ]);
            } catch (NoRouteException) {
                $wordpress->config->update(['expired' => true]);

                return;
            } catch (Throwable $e) {
                if (Str::contains($e->getMessage(), '4222001')) {
                    return; // yoast seo is not activated
                }

                captureException($e);

                return;
            }

            ingest(
                data: [
                    'name' => 'wordpress.article.seo.sync',
                    'source_type' => 'article',
                    'source_id' => $this->articleId,
                    'wordpress_id' => $article->wordpress_id,
                ],
                type: 'action',
            );
        });
    }
}
