<?php

namespace App\Jobs\Scraper;

use App\Builder\ReleaseEventsBuilder;
use App\Models\Media;
use App\Models\Tenants\Article;
use App\Models\Tenants\Scraper;
use App\Models\Tenants\ScraperArticle;
use App\Observers\TriggerSiteRebuildObserver;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;
use RuntimeException;
use Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedById;
use Throwable;

class DownloadScrapedArticlesImages implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Indicate if the job should be marked as failed on timeout.
     *
     * @var bool
     */
    public $failOnTimeout = true;

    /**
     * @var PendingRequest
     */
    protected $http;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(
        protected string $tenantId,
        protected int $scraperId,
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            tenancy()->initialize($this->tenantId);
        } catch (TenantCouldNotBeIdentifiedById) {
            return;
        }

        $scraper = Scraper::find($this->scraperId);

        if ($scraper === null) {
            return;
        }

        $this->http = app('http')
            ->withoutVerifying()
            ->retry(
                3,
                1000,
                fn (Exception $exception) => $exception->getCode() !== 404,
            );

        TriggerSiteRebuildObserver::mute();

        /** @var LazyCollection<int, ScraperArticle> $articles */
        $articles = $scraper->articles()
            ->whereNotNull('article_id')
            ->where('successful', true)
            ->lazyById();

        foreach ($articles as $article) {
            $data = $article->data;

            if (empty($data['heroPhoto']) && empty($data['imageMappings'])) {
                continue;
            }

            $model = Article::find($article->article_id);

            if ($model === null) {
                continue;
            }

            if (!empty($data['heroPhoto'])) {
                $url = $this->download($model->id, 'hero-photo', $data['heroPhoto']);

                if ($url !== null) {
                    $model->cover = [
                        'alt' => '',
                        'caption' => '',
                        'url' => $url,
                    ];
                }
            }

            // images will be a key-url mapping array. key is a unique
            // identify in the src attribute of img tag, we need to
            // replace it with an actual image url.
            if (!empty($data['imageMappings'])) {
                $mapping = array_map(
                    fn (string $source) => $this->download($model->id, 'content-image', $source),
                    $data['imageMappings'],
                );

                $mapping = array_filter($mapping);

                $keys = array_map(
                    fn (string $key) => sprintf('#{%s}', $key),
                    array_keys($mapping),
                );

                $document = str_replace(
                    $keys,
                    array_values($mapping),
                    $model->getAttributes()['document'],
                );

                $model->document = json_decode($document); // @phpstan-ignore-line
            }

            $model->save();
        }

        TriggerSiteRebuildObserver::unmute();

        (new ReleaseEventsBuilder())->handle('site:generate');
    }

    protected function download(int $articleId, string $collection, string $sourceUrl): ?string
    {
        if (Str::contains($sourceUrl, '//images.unsplash.com')) {
            return $sourceUrl;
        }

        $token = unique_token();

        $temp = temp_file();

        try {
            if (Str::startsWith($sourceUrl, 'data:image/')) {
                Image::make($sourceUrl)->save($temp, null, 'jpg');
            } else {
                $this->http->withOptions(['sink' => $temp])->get($sourceUrl);
            }

            $size = getimagesize($temp);

            if ($size !== false) {
                [$width, $height] = $size;
            }

            $to = sprintf(
                'assets/media/images/%s.%s',
                $token,
                Str::afterLast($sourceUrl, '.'),
            );

            $fp = fopen($temp, 'r');

            throw_if($fp === false, new RuntimeException('Failed to open ' . $temp));

            Storage::drive('s3')->put($to, $fp);

            fclose($fp);

            $media = Media::create([
                'model_type' => Article::class,
                'model_id' => $articleId,
                'collection' => $collection,
                'token' => $token,
                'tenant_id' => $this->tenantId,
                'path' => $to,
                'mime' => mime_content_type($temp),
                'size' => filesize($temp),
                'width' => $width ?? 0,
                'height' => $height ?? 0,
            ]);

            return $media->url;
        } catch (Throwable $e) {
            app('log')->error('Unable to download target image.', [
                'tenant_id' => $this->tenantId,
                'article_id' => $articleId,
                'image_url' => $sourceUrl,
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            return $sourceUrl;
        } finally {
            @unlink($temp);
        }
    }
}
