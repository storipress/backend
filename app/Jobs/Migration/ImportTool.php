<?php

namespace App\Jobs\Migration;

use App\Builder\ReleaseEventsBuilder;
use App\Models\Media;
use App\Models\Tenant;
use App\Models\Tenants\Article;
use App\Models\Tenants\Desk;
use App\Models\Tenants\Stage;
use App\Models\Tenants\Tag;
use App\Models\Tenants\User as TenantUser;
use App\Models\User;
use App\Sluggable;
use Exception;
use Generator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\UploadedFile;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;
use Sentry\State\Scope;
use Throwable;
use Webmozart\Assert\Assert;

use function Sentry\captureException;
use function Sentry\configureScope;

/**
 * @see https://github.com/storipress/publication-import-tools/tree/main/common#data-structure
 * @see https://github.com/storipress/publication-import-tools/blob/main/common/sp_import_lib/__init__.py
 *
 * @phpstan-type TAuthor array{
 *     name: string,
 *     email: string,
 * }
 * @phpstan-type TCover array{
 *     url: string,
 *     caption: string|null,
 * }
 * @phpstan-type TDatum array{
 *     title: string,
 *     slug: string|null,
 *     blurb: string|null,
 *     featured: bool,
 *     html: string,
 *     published_at: string|null,
 *     cover: TCover|null,
 *     author: array<int, TAuthor>,
 *     tags: array<int, array{
 *         name: string,
 *         slug: string|null,
 *     }>,
 *     images: array<string, string>,
 *     seo: array{
 *         meta: array{
 *             title: string|null,
 *             description: string|null,
 *         }|null,
 *         og: array{
 *             title: string|null,
 *             description: string|null,
 *         }|null,
 *         og_image: string|null,
 *     }|null,
 * }
 */
class ImportTool implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Carbon $now;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(
        protected string $tenantId,
        protected string $path,
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->now = now();

        $this->configureSentry();

        $tenant = Tenant::find($this->tenantId);
        Assert::isInstanceOf($tenant, Tenant::class);

        $tenant->run(function () {
            $desk = $this->getUncategorizedDesk();

            $defaultStage = $this->getDefaultStage();

            $readyStage = $this->getReadyStage();

            foreach ($this->data() as $datum) {
                try {
                    $users = $this->getTenantUsers($datum['author']);

                    $article = $this->createOrUpdateArticle($datum, $desk, $defaultStage, $readyStage);

                    if (!empty($users)) {
                        $article->authors()->syncWithoutDetaching($users);
                    }

                    if ($datum['cover']) {
                        $this->updateCover($article, $datum['cover']);
                    }

                    $this->updateArticleContent($article, $datum);
                } catch (Throwable $e) {
                    captureException($e);
                }
            }

            (new ReleaseEventsBuilder())->handle('content:import');
        });
    }

    protected function configureSentry(): void
    {
        configureScope(function (Scope $scope) {
            $scope->setContext('payload', [
                'time' => $this->now->toDateTimeString(),
                'path' => $this->path,
            ]);
        });
    }

    protected function getUncategorizedDesk(): Desk
    {
        return Desk::root()->firstOrCreate(['name' => 'Uncategorized']);
    }

    protected function getDefaultStage(): Stage
    {
        return Stage::default()->sole();
    }

    protected function getReadyStage(): Stage
    {
        return Stage::ready()->sole();
    }

    /**
     * @param  array<int, TAuthor>  $authors
     * @return array<int, int>
     */
    protected function getTenantUsers(array $authors): array
    {
        return array_map(function (array $author) {
            $names = explode(' ', $author['name'], 2);

            $user = User::firstOrCreate([
                'email' => $author['email'],
            ], [
                'password' => Hash::make(Str::random()),
                'first_name' => $names[0],
                'last_name' => $names[1] ?? '',
                'signed_up_source' => 'import',
            ]);

            $userId = $user->id;

            TenantUser::firstOrCreate([
                'id' => $userId,
            ], [
                'role' => 'author',
            ]);

            return $userId;
        }, $authors);
    }

    /**
     * @param  TDatum  $datum
     *
     * @throws Exception
     */
    protected function createOrUpdateArticle(array $datum, Desk $desk, Stage $defaultStage, Stage $readyStage): Article
    {
        if (isset($datum['seo']['og_image'])) {
            $datum['seo']['ogImage'] = $datum['seo']['og_image'];

            unset($datum['seo']['og_image']);
        }

        $slug = $datum['slug'] ?: Sluggable::slug($datum['title']);

        $article = Article::withTrashed()->firstOrNew(['slug' => $slug]);

        $article->fill([
            'title' => $datum['title'],
            'blurb' => $datum['blurb'],
            'featured' => $datum['featured'],
            'cover' => $datum['cover'],
            'seo' => $datum['seo'],
            'encryption_key' => base64_encode(random_bytes(32)),
            'published_at' => $datum['published_at'],
        ]);

        if (!$article->exists) {
            $article->order = Article::max('order') + 1;

            $article->desk()->associate($desk);

            $article->stage()->associate(
                $datum['published_at'] ? $readyStage : $defaultStage,
            );
        }

        if ($article->trashed()) {
            $article->deleted_at = null;
        }

        $article->saveQuietly();

        $article->tags()->syncWithoutDetaching(
            array_map(function (array $tag) {
                return Tag::firstOrCreate([
                    'name' => $tag['name'],
                ], [
                    'slug' => $tag['slug'] ?: Sluggable::slug($tag['name']),
                ])->id;
            }, $datum['tags']),
        );

        return $article;
    }

    /**
     * @param  TCover  $cover
     */
    protected function updateCover(Article $article, array $cover): void
    {
        $attributes = $this->downloadImage($cover['url']);

        if ($attributes === null) {
            return;
        }

        $media = Media::create(array_merge($attributes, [
            'model_id' => $article->id,
            'collection' => 'hero-photo',
        ]));

        $cover['url'] = $media->url;

        $article->updateQuietly(['cover' => $cover]);
    }

    /**
     * @param  TDatum  $datum
     */
    protected function updateArticleContent(Article $article, array $datum): void
    {
        $prosemirror = app('prosemirror');

        $original = $this->importContentImages($article->id, $datum['html'], $datum['images']);

        $html = $prosemirror->rewriteHTML($original);

        Assert::notNull($html);

        $emptyDoc = [
            'type' => 'doc',
            'content' => [],
        ];

        $content = $prosemirror->toProseMirror($html);

        Assert::notNull($content);

        $article->document = [
            'default' => $content,
            'title' => $prosemirror->toProseMirror($article->title ?: '') ?: $emptyDoc,
            'blurb' => $prosemirror->toProseMirror($article->blurb ?: '') ?: $emptyDoc,
            'annotations' => [],
        ];

        $article->html = $html;

        $article->plaintext = $prosemirror->toPlainText($content);

        $article->saveQuietly();
    }

    /**
     * @return Generator<int, TDatum>
     */
    protected function data(): Generator
    {
        $fp = fopen($this->path, 'r');

        if (!$fp) {
            throw new RuntimeException('Failed to open the uploaded file.');
        }

        while ($line = fgets($fp)) {
            $data = json_decode($line, true);

            if (!$data) {
                continue;
            }

            yield $data; // @phpstan-ignore-line
        }

        fclose($fp);
    }

    /**
     * @param  array<string, string>  $images
     */
    protected function importContentImages(int $id, string $html, array $images): string
    {
        foreach ($images as $key => $url) {
            $attributes = $this->downloadImage($url);

            if ($attributes !== null) {
                $media = Media::create(array_merge($attributes, [
                    'model_id' => $id,
                    'collection' => 'content-image',
                ]));

                $url = $media->url;
            }

            $html = Str::replace($key, $url, $html);
        }

        Assert::string($html);

        return $html;
    }

    /**
     * @return array{
     *     token: string,
     *     tenant_id: string,
     *     model_type: class-string,
     *     path: string,
     *     mime: string,
     *     size: int,
     *     width: int,
     *     height: int,
     * }|null
     */
    protected function downloadImage(string $url): ?array
    {
        $token = unique_token();

        $temp = temp_file();

        try {
            $downloaded = app('http')
                ->retry(1)
                ->withOptions(['sink' => $temp])
                ->get($url)
                ->ok();
        } catch (RequestException $e) {
            if ($e->getCode() !== 404) {
                captureException($e);
            }
        } catch (ConnectionException $e) {
            if (!Str::contains($e->getMessage(), 'Could not resolve host', true)) {
                captureException($e);
            }
        } catch (Throwable $e) {
            captureException($e);
        }

        if (!isset($downloaded)) {
            return null;
        }

        $path = $this->upload(new UploadedFile($temp, basename($url)), $token);

        [$width, $height] = getimagesize($temp) ?: [0, 0];

        return [
            'token' => $token,
            'tenant_id' => $this->tenantId,
            'model_type' => Article::class,
            'path' => $path,
            'mime' => mime_content_type($temp) ?: 'application/octet-stream',
            'size' => filesize($temp) ?: 0,
            'width' => $width,
            'height' => $height,
        ];
    }

    /**
     * Upload file to AWS S3.
     */
    protected function upload(UploadedFile $file, string $token): string
    {
        $path = $this->path($file->extension(), $token);

        app('aws')->createS3()->putObject([
            'Bucket' => 'storipress',
            'Key' => sprintf('assets/%s', $path),
            'SourceFile' => $file->path(),
            'ContentType' => $file->getMimeType() ?: $file->getClientMimeType(),
        ]);

        return $path;
    }

    /**
     * Get store path.
     */
    protected function path(string $extension, string $token): string
    {
        $chunks = [
            $this->tenantId,
            'migrations',
            $this->now->timestamp,
            $token,
        ];

        $path = implode('/', $chunks);

        return sprintf('%s.%s', $path, $extension);
    }
}
