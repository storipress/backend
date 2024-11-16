<?php

namespace App\GraphQL\Mutations\Upload;

use App\Enums\Upload\Image as UploadImageType;
use App\Events\Entity\Tenant\TenantUpdated;
use App\Exceptions\BadRequestHttpException;
use App\Exceptions\InternalServerErrorHttpException;
use App\Exceptions\NotFoundHttpException;
use App\Models\Media;
use App\Models\Subscriber;
use App\Models\Tenant;
use App\Models\Tenants\Article;
use App\Models\Tenants\Block;
use App\Models\Tenants\Layout;
use App\Models\Tenants\Page;
use App\Models\User;
use Bepsvpt\Blurhash\Facades\BlurHash;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Imagick;
use ImagickException;
use Intervention\Image\Constraint;
use Intervention\Image\Facades\Image;
use Segment\Segment;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;
use Webmozart\Assert\Assert;

class UploadImage
{
    /**
     * @var FilesystemAdapter
     */
    protected $storage;

    /**
     * @param  array{
     *   key: string,
     *   type: UploadImageType,
     *   target_id: string,
     *   signature: string,
     * }  $args
     *
     * @throws Throwable
     */
    public function __invoke($_, array $args): Media
    {
        $this->storage = Storage::drive('s3');

        if ($args['type']->key !== 'userAvatar' && tenant() === null) {
            throw new NotFoundHttpException();
        }

        if ($args['type']->key === 'userAvatar' && (int) $args['target_id'] !== (int) auth()->id()) {
            throw new NotFoundHttpException();
        }

        throw_unless(
            hash_equals(hmac([$args['key']]), $args['signature']),
            new NotFoundHttpException(),
        );

        $path = tenancy()->central(fn () => Cache::pull($args['key']));

        throw_unless(is_string($path), new NotFoundHttpException());

        // download from s3
        $origin = $this->cloudToLocal($path);

        $mime = mime_content_type($origin);

        throw_if(
            $mime === false ||
            ! str_starts_with($mime, 'image/'),
            new BadRequestHttpException(),
        );

        if (str_starts_with($mime, 'image/svg')) { // convert svg to png
            $source = $this->svgToPng($origin);

            $mime = 'image/png';
        } elseif (Str::contains($mime, ['jpg', 'jpeg', 'bmp', 'bitmap'])) { // convert to png
            $source = $this->imageToPng($origin);

            $mime = 'image/png';
        } elseif (
            $mime === 'image/png' &&
            ! Str::containsAll(file_get_contents(filename: $origin, length: 8192) ?: '', ['acTL', 'IDAT'])
        ) {
            $source = $this->imageToPng($origin);
        } elseif ($mime === 'image/gif' && (filesize($origin) >= 4000000) && (tenant() instanceof Tenant) && isset(tenant()->webflow_data['site_id'])) {
            set_time_limit(65);

            $source = $this->gifToWebp($origin);

            $mime = 'image/webp';
        } else {
            $source = $origin;
        }

        $extension = Arr::first((new MimeTypes())->getExtensions($mime));

        Assert::stringNotEmpty($extension);

        $to = sprintf(
            'assets/media/images/%s.%s',
            unique_token(),
            $extension,
        );

        $fp = fopen($source, 'r');

        throw_if($fp === false, new InternalServerErrorHttpException());

        // store to s3
        $this->storage->put($to, $fp);

        fclose($fp);

        $size = getimagesize($source);

        if ($size !== false) {
            [$width, $height] = $size;
        }

        try {
            $blurhash = BlurHash::encode($source);
        } catch (Throwable) {
            // ignore
        }

        $media = Media::create(array_merge(
            $this->toCollection($args['type'], $args['target_id']),
            [
                'token' => unique_token(),
                'tenant_id' => tenant('id'),
                'path' => $to,
                'mime' => $mime,
                'size' => filesize($source),
                'width' => $width ?? 0,
                'height' => $height ?? 0,
                'blurhash' => $blurhash ?? null,
            ],
        ));

        unlink($source);

        if ($args['type']->key === 'userAvatar') {
            $user = auth()->user();

            if ($user instanceof User) {
                $tenantIds = $user->tenants()->pluck('tenants.id')->toArray();

                foreach ($tenantIds as $tenantId) {
                    Segment::track([
                        'userId' => (string) $user->id,
                        'event' => 'user_avatar_uploaded',
                        'context' => [
                            'groupId' => $tenantId,
                        ],
                    ]);
                }
            }
        } elseif ($args['type']->key === 'publicationLogo') {
            Segment::track([
                'userId' => (string) auth()->id(),
                'event' => 'tenant_logo_uploaded',
                'properties' => [
                    'tenant_uid' => tenant('id'),
                    'tenant_name' => tenant('name'),
                ],
                'context' => [
                    'groupId' => tenant('id'),
                ],
            ]);

            // todo: can be removed after implementing the publication setting that allows updating the logo.
            if (is_not_empty_string(tenant('id'))) {
                TenantUpdated::dispatch(tenant('id'), ['logo']);
            }
        } elseif ($args['type']->key === 'publicationFavicon') {
            Segment::track([
                'userId' => (string) auth()->id(),
                'event' => 'tenant_favicon_uploaded',
                'properties' => [
                    'tenant_uid' => tenant('id'),
                    'tenant_name' => tenant('name'),
                ],
                'context' => [
                    'groupId' => tenant('id'),
                ],
            ]);
        }

        return $media;
    }

    /**
     * Download cloud image to local filesystem.
     */
    protected function cloudToLocal(string $path): string
    {
        $local = base_path(
            sprintf('storage/temp/%s', unique_token()),
        );

        file_put_contents(
            $local,
            $this->storage->readStream($path),
        );

        return $local;
    }

    /**
     * Convert svg image to png format.
     */
    protected function svgToPng(string $origin): string
    {
        $path = base_path(
            sprintf('storage/temp/%s.png', unique_token()),
        );

        $this->run(['inkscape', '-h', '1024', $origin, '-o', $path]);

        unlink($origin);

        return $path;
    }

    /**
     * Convert image to png format.
     */
    protected function imageToPng(string $origin): string
    {
        $path = base_path(
            sprintf('storage/temp/%s.png', unique_token()),
        );

        $stream = Image::make($origin)
            ->orientate() // https://www.daveperrett.com/articles/2012/07/28/exif-orientation-handling-is-a-ghetto/
            ->heighten(3840, fn (Constraint $constraint) => $constraint->upsize()) // restrict image size
            ->stream('png');

        file_put_contents($path, $stream);

        unlink($origin);

        return $path;
    }

    /**
     * Compress and convert gif to webp format
     */
    protected function gifToWebp(string $origin): string
    {
        $compress = base_path(
            sprintf('storage/temp/%s.gif', unique_token()),
        );

        $frameNumber = (float) $this->getFrameNumber($origin);

        $frames = [];

        // reduce the frame size to less than 200
        if ($frameNumber > 200.0) {
            $reduceFrame = $frameNumber - 200.0;

            $reduceRate = $frameNumber / $reduceFrame;

            $skip = 1.0;

            for ($i = 0.0; $i < $frameNumber; $i++) {
                if ($i === floor($skip)) {
                    $skip = $skip + $reduceRate;

                    continue;
                }

                $frames[] = sprintf('#%d', $i);
            }
        }

        $this->run(['gifsicle', '--colors=255', '--optimize=2', '--unoptimize', '--resize-fit=768x768', '--output', $compress, $origin, ...$frames], 30.0);

        $webp = base_path(
            sprintf('storage/temp/%s.webp', unique_token()),
        );

        $this->run(['gif2webp', '-mixed', '-q', '35', '-m', '2', '-mt', '-min_size', $compress, '-o', $webp], 30.0);

        unlink($origin);

        unlink($compress);

        return $webp;
    }

    /**
     * Get gif frame number
     *
     * @throws ImagickException
     */
    protected function getFrameNumber(string $origin): int
    {
        $temp = temp_file();

        $this->run(['gifsicle', '--resize-fit=1x1', '--output', $temp, $origin], 15);

        return (new Imagick($temp))->getNumberImages();
    }

    /**
     * Run an external command.
     *
     * @param  array<int, string>  $args
     */
    protected function run(array $args, float $timeout = 10.0): void
    {
        $executableFinder = new ExecutableFinder();

        $args[0] = $executableFinder->find(
            $args[0],
            $args[0],
            ['/usr/bin', '/bin', '/opt/homebrew/bin'],
        );

        $process = new Process(
            command: $args,
            timeout: $timeout,
        );

        $process->run();

        if (! $process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    }

    /**
     * Convert to model collection.
     *
     * @return string[]
     */
    protected function toCollection(UploadImageType $type, string $id): array
    {
        [$model, $collection] = match ($type->key) {
            'userAvatar' => [User::class, 'avatar'],
            'subscriberAvatar' => [Subscriber::class, 'avatar'],
            'articleHeroPhoto' => [Article::class, 'hero-photo'],
            'articleSEOImage' => [Article::class, 'seo-image'],
            'articleContentImage' => [Article::class, 'content-image'],
            'blockPreviewImage' => [Block::class, 'preview-image'],
            'layoutPreviewImage' => [Layout::class, 'preview-image'],
            'publicationLogo' => [Tenant::class, 'publication-logo'],
            'publicationBanner' => [Tenant::class, 'publication-banner'],
            'publicationFavicon' => [Tenant::class, 'publication-favicon'],
            'otherPageContentImage' => [Page::class, 'content-image'],
            default => ['N/A', 'N/A'],
        };

        return [
            'model_type' => $model,
            'model_id' => $id,
            'collection' => $collection,
        ];
    }
}
