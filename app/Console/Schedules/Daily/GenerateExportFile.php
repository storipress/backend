<?php

namespace App\Console\Schedules\Daily;

use App\Console\Schedules\Command;
use App\Models\Media;
use App\Models\Tenant;
use App\Models\Tenants\Article;
use App\Models\Tenants\CustomField;
use App\Models\Tenants\CustomFieldGroup;
use App\Models\Tenants\CustomFieldValue;
use App\Models\Tenants\Desk;
use App\Models\Tenants\Image;
use App\Models\Tenants\Stage;
use App\Models\Tenants\Subscriber;
use App\Models\Tenants\Tag;
use App\Models\Tenants\User;
use App\Models\Tenants\UserActivity;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\Csv\Writer;
use PhpZip\ZipFile;
use Throwable;

use function Sentry\captureException;

class GenerateExportFile extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:generate-export-file';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        ini_set('memory_limit', '-1');

        $date = now()->subDay()->startOfDay()->toDateTimeString();

        $path = storage_path();

        $temp = function ($append) use ($path) {
            $file = Str::lower(sprintf('%s/takeouts/%s', $path, ltrim($append, '/')));

            touch($file);

            return $file;
        };

        $formatter = function ($row) {
            return array_map(function ($value) {
                if ($value instanceof Carbon) {
                    return $value->toISOString();
                } elseif (is_bool($value)) {
                    return $value ? 1 : 0;
                } elseif (is_array($value) && count($value) === count(array_filter($value, 'is_numeric'))) {
                    return implode(',', $value);
                } elseif (is_array($value) && count($value) === count(array_filter($value, 'is_string'))) {
                    return implode(',', $value);
                }

                return $value;
            }, $row);
        };

        runForTenants(function (Tenant $tenant) use ($path, $formatter, $temp, $date) {
            $this->info(sprintf('exporting %s...', $tenant->id));

            $s3 = Str::lower(sprintf('takeouts/storipress-takeout-%s.zip', $tenant->id));

            if (Storage::cloud()->exists($s3)) {
                if (!UserActivity::query()->where('occurred_at', '>=', $date)->exists()) {
                    return;
                }
            }

            File::deleteDirectory(Str::lower(sprintf('%s/takeouts', $path)));

            File::makeDirectory(Str::lower(sprintf('%s/takeouts/images', $path)), recursive: true, force: true);

            $headers = [
                'id',
                'name',
                'description',
                'email',
                'timezone',
                'socials',
                'url',
                'custom_domain',
            ];

            $data = $tenant->only($headers);

            file_put_contents($temp('site.json'), json_encode($data));

            $writer = Writer::createFromPath($temp('site.csv'))->addFormatter($formatter);

            $writer->insertOne($headers);

            $writer->insertOne($data);

            $headers = [
                'id',
                'email',
                'first_name',
                'last_name',
                'full_name',
                'slug',
                'bio',
                'contact_email',
                'job_title',
                'socials',
                'role',
                'socials',
                'avatar',
                'suspended',
                'suspended_at',
                'created_at',
            ];

            $data = User::query()->lazyById()->map(fn (User $user) => $user->only($headers));

            file_put_contents($temp('users.json'), json_encode($data));

            $writer = Writer::createFromPath($temp('users.csv'))->addFormatter($formatter);

            $writer->insertOne($headers);

            $writer->insertAll($data);

            $headers = [
                'id',
                'key',
                'type',
                'name',
                'description',
                'created_at',
                'updated_at',
                'deleted_at',
            ];

            $data = CustomFieldGroup::withTrashed()->lazyById()->map(fn (CustomFieldGroup $group) => $group->only($headers));

            file_put_contents($temp('custom_field_groups.json'), json_encode($data));

            $writer = Writer::createFromPath($temp('custom_field_groups.csv'))->addFormatter($formatter);

            $writer->insertOne($headers);

            $writer->insertAll($data);

            $headers = [
                'id',
                'custom_field_group_id',
                'key',
                'type',
                'name',
                'description',
                'options',
                'created_at',
                'updated_at',
                'deleted_at',
            ];

            $data = CustomField::withTrashed()->lazyById()->map(fn (CustomField $field) => $field->only($headers));

            file_put_contents($temp('custom_fields.json'), json_encode($data));

            $writer = Writer::createFromPath($temp('custom_fields.csv'))->addFormatter($formatter);

            $writer->insertOne($headers);

            $writer->insertAll($data);

            $headers = [
                'id',
                'name',
                'color',
                'icon',
                'order',
                'ready',
                'default',
                'created_at',
                'updated_at',
                'deleted_at',
            ];

            $data = Stage::withTrashed()->lazyById()->map(fn (Stage $stage) => $stage->only($headers));

            file_put_contents($temp('stages.json'), json_encode($data));

            $writer = Writer::createFromPath($temp('stages.csv'))->addFormatter($formatter);

            $writer->insertOne($headers);

            $writer->insertAll($data);

            $headers = [
                'id',
                'sid',
                'desk_id',
                'open_access',
                'name',
                'slug',
                'description',
                'seo',
                'order',
                'created_at',
                'updated_at',
                'deleted_at',
            ];

            $data = Desk::withTrashed()->lazyById()->map(fn (Desk $desk) => $desk->only($headers));

            file_put_contents($temp('desks.json'), json_encode($data));

            $writer = Writer::createFromPath($temp('desks.csv'))->addFormatter($formatter);

            $writer->insertOne($headers);

            $writer->insertAll($data);

            $headers = [
                'id',
                'sid',
                'name',
                'slug',
                'description',
                'created_at',
                'updated_at',
                'deleted_at',
            ];

            $data = Tag::withTrashed()->lazyById()->map(fn (Tag $tag) => $tag->only($headers));

            file_put_contents($temp('tags.json'), json_encode($data));

            $writer = Writer::createFromPath($temp('tags.csv'))->addFormatter($formatter);

            $writer->insertOne($headers);

            $writer->insertAll($data);

            $headers = [
                'id',
                'email',
                'bounced',
                'first_name',
                'last_name',
                'full_name',
                'newsletter',
                'subscribed',
                'verified',
                'verified_at',
                'created_at',
                'updated_at',
            ];

            $data = Subscriber::query()->where('id', '>', 0)->lazyById()->map(fn (Subscriber $subscriber) => $subscriber->only($headers));

            file_put_contents($temp('members.json'), json_encode($data));

            $writer = Writer::createFromPath($temp('members.csv'))->addFormatter($formatter);

            $writer->insertOne($headers);

            $writer->insertAll($data);

            $added = false;

            $headers = [
                'id',
                'sid',
                'desk_id',
                'stage_id',
                'title',
                'slug',
                'pathnames',
                'blurb',
                'order',
                'featured',
                'document',
                'html',
                'plaintext',
                'cover',
                'plan',
                'newsletter',
                'newsletter_at',
                'published_at',
                'created_at',
                'updated_at',
                'deleted_at',
            ];

            $cfHeaders = [];

            $data = Article::withTrashed()->with(['authors', 'tags'])->lazyById()->map(function (Article $article) use ($headers, &$cfHeaders, &$added) {
                $datum = $article->only($headers);

                $datum['author_ids'] = $article->authors->pluck('id')->toArray();

                $datum['tag_ids'] = $article->tags->pluck('id')->toArray();

                $article->custom_fields->map(function (CustomField $field) use (&$datum, &$cfHeaders, $added) {
                    if (!$added) {
                        $cfHeaders[] = 'cf.' . $field->id;
                    }

                    $datum['cf.' . $field->id] = $field->values->map(fn (CustomFieldValue $value) => $value->value);
                });

                $added = true;

                return $datum;
            });

            $headers = array_merge($headers, ['author_ids', 'tag_ids'], $cfHeaders);

            file_put_contents($temp('articles.json'), json_encode($data));

            $writer = Writer::createFromPath($temp('articles.csv'))->addFormatter($formatter);

            $writer->insertOne($headers);

            $writer->insertAll($data);

            Media::query()->where('tenant_id', '=', $tenant->id)->lazyById()->each(function (Media $media) use ($path) {
                try {
                    file_put_contents(Str::lower(sprintf('%s/takeouts/images/%s-%s-%d.%s', $path, $media->collection, $media->model_id, $media->created_at->getTimestamp(), Str::afterLast($media->url, '.'))), file_get_contents($media->url));
                } catch (Throwable) {
                    // ignored
                }
            });

            Image::query()->lazyById()->each(function (Image $image) use ($path) {
                try {
                    file_put_contents(Str::lower(sprintf('%s/takeouts/images/%s-%d-%d.%s', $path, Str::afterLast($image->imageable_type, '\\'), $image->imageable_id, $image->created_at->getTimestamp(), Str::afterLast($image->url, '.'))), file_get_contents($image->url));
                } catch (Throwable) {
                    // ignored
                }
            });

            $zipFile = new ZipFile();

            try {
                $final = Str::lower(sprintf('%s/storipress-takeout-%s.zip', $path, $tenant->id));

                $zipFile
                    ->addDirRecursive(Str::lower(sprintf('%s/takeouts', $path)))
                    ->saveAsFile($final)
                    ->close();

                Storage::cloud()->putFileAs(dirname($s3), $final, basename($s3));

                @unlink($final);
            } catch (Throwable $e) {
                captureException($e);
            } finally {
                $zipFile->close();
            }
        });

        return static::SUCCESS;
    }
}
