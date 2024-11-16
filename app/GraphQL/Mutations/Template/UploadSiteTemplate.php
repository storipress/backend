<?php

namespace App\GraphQL\Mutations\Template;

use App\Builder\ReleaseEventsBuilder;
use App\Enums\Template\Type;
use App\Exceptions\BadRequestHttpException;
use App\Exceptions\InternalServerErrorHttpException;
use App\Exceptions\NotFoundHttpException;
use App\Models\Tenant;
use App\Models\Tenants\Template;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Collection as ElqouentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use wapmorgan\UnifiedArchive\Exceptions\NonExistentArchiveFileException;
use wapmorgan\UnifiedArchive\UnifiedArchive;
use Webmozart\Assert\Assert;

/**
 * @template TSiteTemplateSource of array{
 *     name: string,
 *     file: string,
 *     ssr?: bool,
 * }
 * @template TSiteTemplateResult of array{
 *     key: string,
 *     group: string,
 *     type: Type,
 *     path: string,
 *     name: string,
 * }
 */
class UploadSiteTemplate
{
    /**
     * Cloud storage.
     */
    protected Filesystem $cloud;

    /**
     * Group id for this upload.
     */
    protected string $group;

    /**
     * Cloud storage prefix path for this upload.
     */
    protected string $prefix;

    /**
     * @param  array{
     *     key: string,
     * }  $args
     * @return ElqouentCollection<int, Template>
     */
    public function __invoke($_, array $args): ElqouentCollection
    {
        $this->cloud = Storage::cloud();

        $this->group = sprintf('site-%s', unique_token());

        $this->prefix = sprintf('assets/templates/%s', $this->group);

        $path = tenancy()->central(fn () => Cache::pull($args['key']));

        if (! is_string($path)) {
            throw new NotFoundHttpException();
        }

        if (! $this->cloud->exists($path)) {
            throw new NotFoundHttpException();
        }

        $local = $this->toLocal($path);

        try {
            $result = $this->toCloud($local);
        } finally {
            unlink($local);
        }

        $this->cloud->move(
            $path,
            $to = sprintf('%s/%s.zip', $this->prefix, $site = unique_token()),
        );

        $result->push([ // @phpstan-ignore-line
            'key' => $site,
            'group' => $this->group,
            'type' => Type::site(),
            'path' => $to,
            'name' => 'site-template',
        ]);

        Template::where('group', 'LIKE', 'site-%')->delete();

        $now = now();

        Template::insert($result->map(function (array $data) use ($now) {
            return array_merge($data, [
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        })->toArray());

        $tenant = tenant();

        Assert::isInstanceOf($tenant, Tenant::class);

        $tenant->update([
            'custom_site_template_path' => $to,
        ]);

        if ($tenant->custom_site_template) {
            (new ReleaseEventsBuilder())->handle('karbon:upload');
        }

        return Template::where('group', '=', $this->group)->get();
    }

    protected function toLocal(string $path): string
    {
        // abort if the bundle size is larger than 2MB
        if ($this->cloud->size($path) > 1024 * 1024 * 2) {
            throw new BadRequestHttpException();
        }

        // abort if the bundle isn't zip format
        if ($this->cloud->mimeType($path) !== 'application/zip') {
            throw new BadRequestHttpException();
        }

        $file = temp_file();

        file_put_contents($file, $this->cloud->readStream($path));

        return $file;
    }

    /**
     * @return Collection<int, TSiteTemplateResult>
     */
    protected function toCloud(string $path): Collection
    {
        $archive = UnifiedArchive::open($path);

        if ($archive === null) {
            throw new InternalServerErrorHttpException();
        }

        try {
            $content = $archive->getFileContent('.storipress/storipress.json');
        } catch (NonExistentArchiveFileException) {
            throw new BadRequestHttpException();
        }

        /**
         * @var array<string, TSiteTemplateSource[]>|false|null $meta
         */
        $meta = json_decode($content, true);

        if (empty($meta)) {
            throw new BadRequestHttpException();
        }

        $types = [
            'blocks' => Type::editorBlock(),
            'layouts' => Type::articleLayout(),
        ];

        /** @var Collection<int, TSiteTemplateResult> $items */
        $items = new Collection();

        foreach ($types as $name => $type) {
            try {
                $items->push(...$this->upload($archive, $meta[$name], $type));
            } catch (NonExistentArchiveFileException) {
                throw new BadRequestHttpException();
            }
        }

        return $items;
    }

    /**
     * @param  array<TSiteTemplateSource>  $items
     * @return array<TSiteTemplateResult>
     *
     * @throws NonExistentArchiveFileException
     */
    protected function upload(UnifiedArchive $archive, array $items, Type $type): array
    {
        // @phpstan-ignore-next-line
        return array_map(function (array $item) use ($archive, $type) {
            $file = sprintf('.storipress/%s', ltrim($item['file'], './'));

            $key = unique_token();

            $path = sprintf('%s/%s.js', $this->prefix, $key);

            $this->cloud->put($path, $archive->getFileStream($file));

            return [
                'key' => $key,
                'group' => $this->group,
                'type' => Type::editorBlock()->isNot($type)
                    ? $type
                    : (($item['ssr'] ?? false) ? Type::editorBlockSsr() : $type),
                'path' => $path,
                'name' => $item['name'],
            ];
        }, $items);
    }
}
