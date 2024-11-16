<?php

namespace App\Jobs;

use App\Builder\ReleaseEventsBuilder;
use App\Enums\Article\PublishType;
use App\Enums\CustomField\GroupType;
use App\Enums\CustomField\ReferenceTarget;
use App\Models\Media;
use App\Models\Tenant;
use App\Models\Tenants\Article;
use App\Models\Tenants\CustomField;
use App\Models\Tenants\CustomFieldGroup;
use App\Models\Tenants\CustomFieldValue;
use App\Models\Tenants\Desk;
use App\Models\Tenants\Stage;
use App\Models\Tenants\Tag;
use App\Models\Tenants\User as TenantUser;
use App\Models\User;
use App\Notifications\Migration\WordPressFailedNotification;
use App\Notifications\Migration\WordPressProgressUpdatedNotification;
use App\Notifications\Migration\WordPressStartedNotification;
use App\Notifications\Migration\WordPressSucceededNotification;
use App\Observers\RudderStackSyncingObserver;
use App\Observers\TriggerSiteRebuildObserver;
use App\Queue\Middleware\WithoutOverlapping;
use App\Sluggable;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterval;
use Generator;
use GuzzleHttp\Exception\TooManyRedirectsException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\UploadedFile;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Stringable;
use RuntimeException;
use Segment\Segment;
use Sentry\State\Scope;
use Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedById;
use Symfony\Component\Mime\MimeTypes;
use Throwable;
use Webmozart\Assert\Assert;

use function Sentry\captureException;
use function Sentry\withScope;

/**
 * @phpstan-type TPayload array{
 *     version: string,
 *     type: string,
 *     data: array<string, int|string>,
 * }
 * @phpstan-type TPostPayload array{
 *     id: string,
 *     author_id: string,
 *     title: string,
 *     slug: string,
 *     excerpt: string|null,
 *     content: string,
 *     categories?: array<int, int>,
 *     tags?: array<int, int>,
 *     status: string,
 *     created_at: int,
 *     updated_at: int,
 *     permalink: string,
 *     metadata?: array<array-key, array<int, string>>,
 *     category?: array<int, int>|null,
 *     post_tag?: array<int, int>|null,
 * }
 * @phpstan-type TAttachmentMetadata array{
 *     width?: int,
 *     height?: int,
 *     file: string,
 *     filesize: int,
 *     sizes?: array<array-key, array{
 *         file: string,
 *         width: int,
 *         height: int,
 *         mime-type: string,
 *         filesize: int,
 *     }>,
 * }
 */
class ImportContentFromOtherCMS implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 0;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 1;

    protected string $path;

    /**
     * @var array<int, string>
     */
    protected array $types = [
        'site',
        'user',
        'category',
        'tag',
        'post',
        'articulo',
    ];

    /**
     * @var array<string, string>
     */
    protected array $acfMapping = [
        'image' => 'file',
        'text' => 'text',
        'true_false' => 'boolean',
        'taxonomy' => 'reference',
        'oembed' => 'url',
        'url' => 'url',
        'select' => 'select',
        'textarea' => 'text',
    ];

    /**
     * @var array<int, string>
     */
    protected array $blacklist = ['imailfree.cc'];

    protected CarbonImmutable $now;

    protected Tenant $tenant;

    protected PendingRequest $http;

    protected string $host;

    protected int $defaultStageId;

    protected int $readyStageId;

    protected int $defaultDeskId;

    /**
     * @var array{
     *     custom_field_groups: array<array-key, int>,
     *     custom_fields: array<array-key, array{id: int, type: string, target: string|null}>,
     *     users: array<array-key, int>,
     *     categories: array<array-key, int>,
     *     category_parents: array<array-key, int>,
     *     category_children: array<array-key, int>,
     *     tags: array<array-key, int>,
     *     posts: array<array-key, int>,
     *     covers: array<array-key, array<int, int>>,
     *     attachments: array<array-key, array{path: string, caption: string|null, alt: string|null}>,
     * }
     */
    protected array $mapping = [
        'custom_field_groups' => [],
        'custom_fields' => [],
        'users' => [],
        'categories' => [],
        'category_parents' => [],
        'category_children' => [],
        'tags' => [],
        'posts' => [],
        'covers' => [],
        'attachments' => [],
    ];

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected string $tenantId,
        protected string $filename,
    ) {
        //
    }

    /**
     * Get the middleware the job should pass through.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->tenantId))->dontRelease()];
    }

    /**
     * Execute the job.
     *
     *
     * @throws TenantCouldNotBeIdentifiedById
     */
    public function handle(): void
    {
        // ensure tenant exists and initialized
        $tenant = Tenant::with('owner')
            ->where('id', '=', $this->tenantId)
            ->where('initialized', '=', true)
            ->first();

        if (!($tenant instanceof Tenant)) {
            return;
        }

        $this->path = temp_file();

        $this->tenant = $tenant;

        $this->rudderstack('tenant_wordpress_import_started');

        $this->tenant->run(function () {
            try {
                file_put_contents($this->path, Storage::drive('nfs')->readStream($this->filename));

                $progress = 0;

                $cmd = sprintf('wc -l %s 2>/dev/null', escapeshellarg($this->path));

                $lines = exec($cmd);

                $total = intval(ceil(intval(trim($lines ?: '')) * 1.1));

                $this->tenant->owner->notify(new WordPressStartedNotification($this->tenant->id, $this->tenant->name));

                $this->setUp();

                $groups = $fields = [];

                foreach ($this->lines() as $line) {
                    /** @var TPayload|false|null $payload */
                    $payload = json_decode($line, true);

                    if (empty($payload)) {
                        continue;
                    }

                    if ($payload['type'] === 'acf-field-group') {
                        $groups[] = $payload['data'];
                    } elseif ($payload['type'] === 'acf-field') {
                        $fields[] = $payload['data'];
                    } elseif ($payload['type'] === 'attachment') {
                        $this->attachment($payload['data']); // @phpstan-ignore-line
                    }
                }

                foreach ($groups as $data) {
                    $this->acfFieldGroup($data); // @phpstan-ignore-line
                }

                foreach ($fields as $data) {
                    $this->acfField($data); // @phpstan-ignore-line
                }

                foreach ($this->lines() as $idx => $line) {
                    if ($total !== 0) {
                        $current = intval($idx / $total * 100);

                        if ($current > $progress) {
                            $this->tenant->owner->notify(new WordPressProgressUpdatedNotification($this->tenant->id, $current));

                            $progress = $current;
                        }
                    }

                    /** @var TPayload|false|null $payload */
                    $payload = json_decode($line, true);

                    if (empty($payload)) {
                        continue;
                    }

                    $type = $payload['type'];

                    if (!in_array($type, $this->types, true)) {
                        continue;
                    }

                    if (!method_exists($this, $type)) {
                        return;
                    }

                    $this->{$type}($payload['data']);
                }

                $this->pullCovers();

                Article::makeAllSearchable(100);

                (new ReleaseEventsBuilder())->handle('content:import', ['cms' => 'wordpress']);

                $elapsedTime = (int) now()->timestamp - (int) $this->now->timestamp;

                $this->rudderstack('tenant_wordpress_import_succeed', [
                    'elapsed_time' => $elapsedTime,
                    'elapsed_time_human_readable' => CarbonInterval::seconds($elapsedTime)
                        ->cascade()
                        ->forHumans(),
                ]);

                $this->tenant->owner->notify(new WordPressSucceededNotification($this->tenant->id, $this->tenant->name, ['articles' => count($this->mapping['posts'])]));
            } catch (Throwable $e) {
                withScope(function (Scope $scope) use ($e) {
                    $scope->setContext('wordpress', [
                        'tenant' => $this->tenant->id,
                        'file' => $this->filename,
                    ]);

                    captureException($e);
                });

                $this->rudderstack('tenant_wordpress_import_failed');

                $this->tenant->owner->notify(new WordPressFailedNotification($this->tenant->id, $this->tenant->name));

                $elapsedTime = (int) now()->timestamp - (int) $this->now->timestamp;
            } finally {
                $this->tearDown();
            }

            Log::channel('slack')->debug(
                sprintf('Import content finished（%s）', isset($e) ? 'fail' : 'success'),
                [
                    'message' => isset($e) ? $e->getMessage() : null,
                    'env' => app()->environment(),
                    'tenant' => $this->tenant->id,
                    'domain' => $this->host,
                    'file' => $this->filename,
                    'memory usage (MB)' => number_format(memory_get_usage() / 1024 / 1024, 2),
                    'users' => count($this->mapping['users']),
                    'posts' => count($this->mapping['posts']),
                    'categories' => count($this->mapping['categories']),
                    'tags' => count($this->mapping['tags']),
                    'attachments' => count($this->mapping['attachments']),
                    'elapsed time' => CarbonInterval::seconds($elapsedTime)
                        ->cascade()
                        ->forHumans(),
                ],
            );

            $path = base_path(sprintf('storage/temp/wordpress-import-%s.json', $this->tenant->id));

            file_put_contents($path, json_encode([
                'tenant' => $this->tenant->id,
                'domain' => $this->host,
                'file' => $this->filename,
                ...$this->mapping,
            ]));
        });
    }

    protected function setUp(): void
    {
        ini_set('memory_limit', '256M');

        $this->pauseEvents();

        $this->now = now()->toImmutable();

        $this->defaultStageId = Stage::default()->sole()->id;

        $this->readyStageId = Stage::ready()->sole()->id;

        $this->defaultDeskId = Desk::firstOrCreate(['desk_id' => null, 'name' => 'Uncategorized'])->id;
    }

    protected function tearDown(): void
    {
        $this->resumeEvents();
    }

    protected function pauseEvents(): void
    {
        RudderStackSyncingObserver::mute();

        TriggerSiteRebuildObserver::mute();

        Article::disableSearchSyncing();
    }

    protected function resumeEvents(): void
    {
        Article::enableSearchSyncing();

        TriggerSiteRebuildObserver::unmute();

        RudderStackSyncingObserver::unmute();
    }

    /**
     * Get line from the uploaded file.
     *
     * @return Generator<int, string>
     */
    protected function lines(): Generator
    {
        $fp = fopen($this->path, 'r');

        if ($fp === false) {
            throw new RuntimeException('Failed to open the uploaded file.');
        }

        while (($line = fgets($fp)) !== false) {
            $trim = trim($line);

            if (!empty($trim)) {
                yield $trim;
            }
        }

        fclose($fp);
    }

    /**
     * @param  array{
     *     id: string,
     *     title: string,
     *     excerpt: string,
     *     content: string,
     * }  $data
     */
    protected function acfFieldGroup(array $data): void
    {
        /**
         * @var array{
         *     location: array<array-key, array<array-key, array{
         *         param: string,
         *         operator: string,
         *         value: string,
         *     }>>,
         *     description: string
         * }|false $meta
         */
        $meta = unserialize(strip_tags(trim($data['content'] ?: '')));

        if (!is_array($meta) || !is_array($meta['location'])) {
            return;
        }

        $isPost = Arr::first($meta['location'], function ($checker) {
            return $checker[0]['param'] === 'post_type' && $checker[0]['value'] === 'post';
        });

        if ($isPost === null) {
            return;
        }

        $group = CustomFieldGroup::withTrashed()->updateOrCreate([
            'key' => $data['excerpt'],
        ], [
            'type' => GroupType::articleMetafield(),
            'name' => $data['title'],
            'description' => trim($meta['description']) ?: null,
            'deleted_at' => null,
        ]);

        $this->mapping['custom_field_groups'][$data['id']] = $group->id;
    }

    /**
     * @param  array{
     *     post_id: string,
     *     title: string,
     *     slug: string,
     *     excerpt: string,
     *     content: string,
     * }  $data
     */
    protected function acfField(array $data): void
    {
        if (!isset($this->mapping['custom_field_groups'][$data['post_id']])) {
            return;
        }

        $chunks = explode(PHP_EOL, trim($data['content'] ?: ''));

        foreach ($chunks as &$chunk) {
            if (Str::startsWith($chunk, '<p>') && Str::endsWith($chunk, '</p>')) {
                $chunk = substr($chunk, 3, -4);
            }
        }

        $content = implode('', $chunks);

        /**
         * @var array{
         *     type: string,
         *     instructions: string,
         *     required: int,
         *     placeholder?: string,
         *     field_type?: string,
         *     choices?: array<string, string>,
         *     taxonomy?: string,
         * }|false $meta
         */
        $meta = @unserialize($content);

        if (!is_array($meta)) {
            return;
        }

        if (!isset($this->acfMapping[$meta['type']])) {
            return;
        }

        $type = $this->acfMapping[$meta['type']];

        $options = [
            'type' => $type,
            'repeat' => false,
            'required' => $meta['required'] === 1,
            'placeholder' => ($meta['placeholder'] ?? '') ?: null,
            'multiple' => in_array($meta['field_type'] ?? '', ['multi_select', 'checkbox'], true),
            'choices' => $meta['choices'] ?? null,
            'acf' => $meta,
        ];

        if ($meta['type'] === 'textarea') {
            $options['multiline'] = true;
        }

        if ($type === 'reference' && isset($meta['taxonomy'])) {
            $options['target'] = match ($meta['taxonomy']) {
                'post_tag' => ReferenceTarget::tag,
                default => null,
            };
        }

        $field = CustomField::withTrashed()->updateOrCreate([
            'key' => $data['excerpt'],
        ], [
            'custom_field_group_id' => $this->mapping['custom_field_groups'][$data['post_id']],
            'type' => $type,
            'name' => $data['title'],
            'description' => trim($meta['instructions']) ?: null,
            'options' => $options,
            'deleted_at' => null,
        ]);

        $this->mapping['custom_fields'][$data['slug']] = [
            'id' => $field->id,
            'type' => $type,
            'target' => $options['target'] ?? null,
        ];
    }

    /**
     * @param  array{
     *     name: string,
     *     description: string,
     *     url: string,
     *     uploads_url: string,
     * }  $data
     */
    protected function site(array $data): void
    {
        $this->http = app('http2')
            ->connectTimeout(7)
            ->timeout(15)
            ->withoutVerifying()
            ->accept('*/*')
            ->baseUrl(
                Str::of($data['uploads_url'])
                    ->finish('/')
                    ->replace('http://', 'https://')
                    ->toString(),
            );

        $host = parse_url($data['url'], PHP_URL_HOST);

        Assert::stringNotEmpty($host);

        $this->host = Str::lower($host);

        $this->tenant->update([
            'name' => $data['name'],
            'description' => $data['description'],
        ]);
    }

    /**
     * @param  array{
     *     ID: string,
     *     user_email: string,
     *     display_name: string,
     *     caps?: array<string, bool>,
     * }  $data
     */
    protected function user(array $data): void
    {
        $notWriter = collect($data['caps'] ?? [])
            ->only(['administrator', 'editor', 'author', 'contributor'])
            ->filter()
            ->isEmpty();

        if ($notWriter) {
            return;
        }

        $email = trim($data['user_email']);

        if (empty($email)) {
            return;
        }

        if (Str::contains($email, $this->blacklist, true)) {
            return;
        }

        if ($data['ID'] === '1') {
            $user = $this->tenant->owner;
        } else {
            $user = User::where('email', '=', $email)->first();
        }

        $names = explode(' ', $data['display_name'], 2);

        if ($user === null) {
            $user = User::create([
                'email' => $email,
                'password' => Str::random(32),
                'first_name' => $names[0],
                'last_name' => $names[1] ?? '',
                'signed_up_source' => sprintf('invite:%s', $this->tenantId),
            ]);
        } elseif ($user->full_name === null) {
            $user->update([
                'first_name' => $names[0],
                'last_name' => $names[1] ?? '',
            ]);
        }

        $this->mapping['users'][$data['ID']] = $user->id;

        if (TenantUser::where('id', '=', $user->id)->exists()) {
            return;
        }

        TenantUser::create([
            'id' => $user->id,
            'role' => 'author',
        ]);

        $user->tenants()->attach($this->tenantId, ['role' => 'author']);
    }

    /**
     * @param  array{
     *     term_id: int,
     *     name: string,
     *     slug: string,
     *     parent: int,
     * }  $data
     */
    protected function category(array $data): void
    {
        // `category_parents` is a one-dimensional array that stores a tree structure,
        // where the key represents the current category, and the value represents the
        // parent category of that category. When the value is `0`, it indicates a
        // top-level category. Since desks will have at most one sub-desk, it is
        // necessary to map all second-level or deeper categories to the first level.
        // Meanwhile, `category_children` is used to mark whether a category has child
        // categories. For example:
        // Apple (id 1, parent_id 0)
        // - Banana (id 2, parent_id 1)
        //  - Car (id 3, parent_id 2)
        $parent = $this->mapping['category_parents'][$data['term_id']] = $data['parent'];

        if ($parent > 0) {
            $this->mapping['category_children'][$parent] = 1;

            while ($this->mapping['category_parents'][$parent] > 0) {
                $parent = $this->mapping['category_parents'][$parent];
            }
        }

        // use slug first, if empty, use name instead
        $slug = Str::of($data['slug'])
            ->trim()
            ->whenEmpty(fn (Stringable $s) => $s->append($data['name']))
            ->trim()
            ->lower()
            ->toString();

        if (in_array($slug, ['all', 'mime', 'latest', 'featured'], true)) {
            $slug = sprintf('wp-%s', $slug);
        }

        $desk = Desk::withTrashed()->updateOrCreate([
            'slug' => $slug,
        ], [
            'desk_id' => $parent === 0 ? null : $this->mapping['categories'][$parent],
            'name' => html_entity_decode($data['name']),
            'deleted_at' => null,
        ]);

        Assert::isInstanceOf($desk, Desk::class);

        $this->mapping['categories'][$data['term_id']] = $desk->id;

        if ($desk->desk_id !== null) {
            return;
        }

        $desk->users()->syncWithoutDetaching($this->mapping['users']);
    }

    /**
     * @param  array{
     *     term_id: int,
     *     name: string,
     *     slug: string,
     * }  $data
     */
    protected function tag(array $data): void
    {
        $name = html_entity_decode($data['name']);

        $slug = Sluggable::slug($name);

        try {
            $tag = Tag::withTrashed()->updateOrCreate([
                'name' => $name,
            ], [
                'slug' => $data['slug'],
                'deleted_at' => null,
            ]);
        } catch (UniqueConstraintViolationException) {
            $tag = Tag::withTrashed()
                ->where('slug', '=', $slug)
                ->sole();
        }

        $this->mapping['tags'][$data['term_id']] = $tag->id;
    }

    /**
     * @param  TPostPayload  $data
     */
    public function articulo(array $data): void
    {
        $this->post($data);
    }

    /**
     * @param  TPostPayload  $data
     */
    protected function post(array $data): void
    {
        $categories = $data['categories'] ?? ($data['category'] ?? []);

        if (empty($categories)) {
            $categories = [];
        }

        $metadata = $data['metadata'] ?? [];

        foreach ($metadata as &$items) {
            $items = array_values(
                array_filter(
                    $items,
                    fn (mixed $item) => is_string($item) && Str::length($item) > 0,
                ),
            );
        }

        $metadata = array_filter($metadata);

        $coverId = Arr::first($metadata['_thumbnail_id'] ?? []);

        if (!is_not_empty_string($coverId)) {
            $coverId = null;
        }

        $published = $data['status'] === 'publish';

        $slug = trim($data['slug']);

        if (empty($slug)) {
            $slug = Sluggable::slug($data['title']);
        }

        $blurb = trim($data['excerpt'] ?: '') ?: null;

        if ($blurb === null) {
            $theme = Arr::first($metadata['td_post_theme_settings'] ?? []);

            if (is_not_empty_string($theme)) {
                $theme = unserialize($theme);

                if (is_array($theme) && isset($theme['td_subtitle']) && is_string($theme['td_subtitle'])) {
                    $blurb = $theme['td_subtitle'];
                }
            }
        }

        $article = Article::withTrashed()
            ->firstOrNew([
                'slug' => Str::limit($slug, 230, ''),
            ], [
                'encryption_key' => base64_encode(random_bytes(32)),
            ]);

        $article->timestamps = false;

        Assert::isInstanceOf($article, Article::class);

        $article->desk_id = $this->desk($categories);

        $article->stage_id = $published ? $this->readyStageId : $this->defaultStageId;

        $article->title = trim($data['title']) ?: 'Untitled';

        $article->blurb = $blurb;

        $article->order = Article::max('order') + 1;

        $article->seo = $this->seo($metadata); // @phpstan-ignore-line

        if (!empty(empty($data['permalink']))) {
            $pathnames = $article->pathnames ?: [];

            $pathnames[$this->now->timestamp] = $data['permalink'];

            $article->pathnames = $pathnames;
        }

        $article->publish_type = $published ? PublishType::immediate() : PublishType::none();

        if ($published) {
            $serialized = Arr::first($metadata['_schema_json'] ?? []) ?: serialize([]);

            $schema = is_string($serialized) ? unserialize($serialized) : null;

            if (is_array($schema) && isset($schema['datePublished'])) {
                $article->published_at = Carbon::parse($schema['datePublished']);
            } else {
                $article->published_at = Carbon::createFromTimestampUTC($data['updated_at']);
            }
        }

        $article->created_at = Carbon::createFromTimestampUTC(($data['created_at'] ?: $data['updated_at']) ?: $this->now->timestamp);

        $article->updated_at = Carbon::createFromTimestampUTC($data['updated_at'] ?: $this->now->timestamp);

        $article->deleted_at = null;

        $article->saveQuietly();

        $this->mapping['posts'][$data['id']] = $article->id;

        $content = trim($data['content'] ?: '');

        if (empty($content) && !empty($metadata['_themify_builder_settings_json'])) {
            $encodedThemify = Arr::first($metadata['_themify_builder_settings_json'], default: '');

            if (is_not_empty_string($encodedThemify)) {
                $themify = json_decode($encodedThemify, true);

                if (is_array($themify) && !empty($themify)) {
                    $text = Arr::first(Arr::dot($themify), function ($value, string $key) {
                        return Str::contains($key, 'content_text') && !empty($value);
                    });

                    if (is_not_empty_string($text)) {
                        $decodedText = json_decode(sprintf('"%s"', $text));

                        if (is_not_empty_string($decodedText)) {
                            $content = $decodedText;
                        }
                    }
                }
            }
        }

        $this->contentToDocument($content, $article, $coverId);

        $tags = $data['tags'] ?? ($data['post_tag'] ?? []);

        if (!empty($tags)) {
            $article->tags()->syncWithoutDetaching(
                array_filter(
                    array_map(
                        fn ($id) => $this->mapping['tags'][$id] ?? 0,
                        $tags,
                    ),
                ),
            );
        }

        try {
            // assign the user to article's author list
            $article->authors()->syncWithoutDetaching(
                [$this->mapping['users'][$data['author_id']] ?? $this->tenant->owner->id],
            );
        } catch (QueryException $e) {
            // the team member was removed from the publication
            if (!Str::contains($e->getMessage(), 'Integrity constraint violation: 1452')) {
                throw $e;
            }

            // remove the user from the users mapping
            unset($this->mapping['users'][$data['author_id']]);
        }

        if (!empty($coverId)) {
            $this->mapping['covers'][$coverId][] = $article->id;
        }

        // ACF custom fields

        $mapping = [
            Article::class => 'posts',
            Desk::class => 'categories',
            User::class => 'users',
            Tag::class => 'tags',
        ];

        foreach ($metadata as $key => $fields) {
            $field = Arr::first($fields);

            if (!is_not_empty_string($field)) {
                continue;
            }

            if (!isset($this->mapping['custom_fields'][$field])) {
                continue;
            }

            $key = Str::substr($key, 1);

            if (!isset($metadata[$key])) {
                continue;
            }

            $serialized = Arr::first($metadata[$key]);

            if (!is_not_empty_string($serialized)) {
                continue;
            }

            $value = @unserialize($serialized);

            if ($value === false) {
                $value = $serialized;
            }

            if ($this->mapping['custom_fields'][$field]['type'] === 'reference' && is_array($value)) {
                $key = $mapping[$this->mapping['custom_fields'][$field]['target']];

                $value = array_map(function ($idx) use ($key) {
                    return $this->mapping[$key][$idx] ?? null;
                }, $value);

                $value = array_values(array_filter($value));
            } elseif ($this->mapping['custom_fields'][$field]['type'] === 'file') {
                $url = $this->fetch($this->mapping['attachments'][$value]['path'], $article->id, 'custom-field-image');

                $value = [
                    'key' => Str::after($url, 'https://assets.stori.press/'),
                    'url' => $url,
                    'size' => (int) (array_change_key_case(get_headers($url, true) ?: [])['content-length'] ?? 0),
                    'mime_type' => Arr::first((new MimeTypes())->getMimeTypes(pathinfo($url, PATHINFO_EXTENSION)), default: 'application/octet-stream'),
                ];
            } elseif ($this->mapping['custom_fields'][$field]['type'] === 'select') {
                $value = Arr::wrap($value);
            }

            CustomFieldValue::firstOrCreate([
                'custom_field_id' => $this->mapping['custom_fields'][$field]['id'],
                'custom_field_morph_id' => $article->id,
                'custom_field_morph_type' => Article::class,
                'type' => $this->mapping['custom_fields'][$field]['type'],
                'value' => $value,
            ]);
        }
    }

    /**
     * Get best match desk id.
     *
     * @param  array<int, int>  $categories
     */
    protected function desk(array $categories): int
    {
        if (empty($categories)) {
            return $this->defaultDeskId;
        }

        if (count($categories) === 1) {
            return $this->mapping['categories'][$categories[0]] ?? $this->defaultDeskId;
        }

        // find the first category that has a parent and no children
        $category = Arr::first($categories, function (int $category) {
            return $this->mapping['category_parents'][$category] > 0 &&
                   !isset($this->mapping['category_children'][$category]);
        });

        if ($category !== null) {
            return $this->mapping['categories'][$category];
        }

        return $this->mapping['categories'][Arr::first($categories)];
    }

    /**
     * Get seo information.
     *
     * @param  array<array-key, array<int, string>>  $meta
     * @return array<string, mixed>|null
     */
    protected function seo(array $meta): ?array
    {
        if (!empty($meta['_yoast_wpseo_title']) || !empty($meta['_yoast_wpseo_metadesc'])) {
            return $this->yoastSeo($meta);
        }

        if (!empty($meta['rank_math_title']) || !empty($meta['rank_math_description'])) {
            return $this->rankMathSeo($meta);
        }

        return null;
    }

    /**
     * @param  array{
     *     _yoast_wpseo_title?: array<int, string>,
     *     _yoast_wpseo_metadesc?: array<int, string>,
     * }  $meta
     * @return array<string, mixed>
     */
    protected function yoastSeo(array $meta): array
    {
        return [
            'ogImage' => '',
            'meta' => [
                'title' => Arr::first($meta['_yoast_wpseo_title'] ?? []),
                'description' => Arr::first($meta['_yoast_wpseo_metadesc'] ?? []),
            ],
        ];
    }

    /**
     * @param  array{
     *     rank_math_title?: array<int, string>,
     *     rank_math_description?: array<int, string>,
     * }  $meta
     * @return array<string, mixed>
     */
    protected function rankMathSeo(array $meta): array
    {
        return [
            'ogImage' => '',
            'meta' => [
                'title' => Arr::first($meta['rank_math_title'] ?? []),
                'description' => Arr::first($meta['rank_math_description'] ?? []),
            ],
        ];
    }

    /**
     * Convert WordPress post content to Storipress document.
     */
    protected function contentToDocument(?string $content, Article $article, ?string $coverId): void
    {
        $prosemirror = app('prosemirror');

        $content = trim($content ?: '');

        if (is_not_empty_string($coverId)) {
            $content = $this->removeHeroPhotoFromContent($content, $coverId);
        }

        $html = $this->pullContentImages($content, $article);

        $html = $this->rewriteContentLinks($html);

        $html = $this->rewriteQueryStringLinks($html);

        $html = $this->removeEmptyLines($html);

        $rewritten = $prosemirror->rewriteHTML($html, ['wordpress']);

        Assert::string($rewritten);

        $document = $prosemirror->toProseMirror($rewritten);

        Assert::notNull($document);

        $article->html = $rewritten;

        $article->plaintext = $prosemirror->toPlainText($document);

        $emptyDoc = [
            'type' => 'doc',
            'content' => [],
        ];

        $article->document = [
            'default' => $document,
            'title' => $prosemirror->toProseMirror($article->title ?: '') ?: $emptyDoc,
            'blurb' => $prosemirror->toProseMirror($article->blurb ?: '') ?: $emptyDoc,
            'annotations' => [],
        ];

        $article->saveQuietly();
    }

    protected function removeHeroPhotoFromContent(string $content, string $coverId): string
    {
        $matcher = sprintf('wp-image-%s', $coverId);

        $lines = explode(PHP_EOL, $content);

        for ($i = 0; $i < 5; ++$i) {
            if (!isset($lines[$i])) {
                break;
            }

            if (!Str::contains($lines[$i], $matcher, true)) {
                continue;
            }

            $lines[$i] = '';
        }

        return implode(PHP_EOL, array_filter($lines));
    }

    protected function pullContentImages(string $content, Article $article): string
    {
        // get all img tag
        $count = preg_match_all('/<img[^>]+>/i', $content, $tags);

        if ($count === false || $count === 0) {
            return $content;
        }

        $images = [];

        // extract src value from img tag
        foreach ($tags[0] as $tag) {
            if (!preg_match('/src="([^"]+)"/i', $tag, $match)) {
                continue;
            }

            $images[] = $match[1];
        }

        $images = array_values(array_unique($images));

        if (empty($images)) {
            return $content;
        }

        foreach ($images as $link) {
            $url = $this->fetch($link, $article->id, 'content-image');

            if ($link === $url) {
                continue;
            }

            $content = Str::replace($link, $url, $content);
        }

        Assert::string($content);

        return $content;
    }

    protected function rewriteContentLinks(string $html): string
    {
        // the following links will be rewritten
        // - href="https://example.com/
        // - href="https://example.com
        // - href="http://example.com/
        // - href="http://example.com
        // - href="//example.com/
        // - href="//example.com

        $replaced = preg_replace(
            sprintf('/href="(?:https?:)?\/\/%s\/?/i', preg_quote($this->host)),
            'href="/',
            $html,
        );

        if (!is_string($replaced)) {
            return $html;
        }

        return $replaced;
    }

    protected function rewriteQueryStringLinks(string $html): string
    {
        $count = preg_match_all('/href="\/\?p=(\d+)"/', $html, $matches);

        if ($count === false || $count === 0) {
            return $html;
        }

        foreach ($matches[1] as $postId) {
            if (empty($this->mapping['posts'][$postId])) {
                continue;
            }

            $slug = Article::where('id', '=', $this->mapping['posts'][$postId])->value('slug');

            if (!is_not_empty_string($slug)) {
                continue;
            }

            $html = Str::replace(
                sprintf('href="/?p=%s"', $postId),
                sprintf('href="/%s"', $slug),
                $html,
            );
        }

        Assert::string($html);

        return $html;
    }

    protected function removeEmptyLines(string $html): string
    {
        // remove <br> tags
        $replaced = preg_replace('/<br[^>]*>/ui', '', $html);

        if ($replaced === null) {
            return $html;
        }

        // remove empty tags
        do {
            $replaced = preg_replace(
                '/<\w+?[^\/?>]*?>[\s\x{200B}-\x{200D}\x{FEFF}]*?<\/\w+?>/ui',
                '',
                $replaced,
                -1,
                $count,
            );
        } while ($replaced !== null && $count !== 0);

        if ($replaced === null) {
            return $html;
        }

        return $replaced;
    }

    /**
     * @param  array{
     *     id: string,
     *     post_id: string|null,
     *     excerpt: string|null,
     *     mime_type?: string,
     *     metadata?: array{
     *         _wp_attached_file?: array<int, string>,
     *         _wp_attachment_metadata?: array<int, string>,
     *         _wp_attachment_image_alt?: array<int, string>,
     *     },
     * }  $data
     */
    protected function attachment(array $data): void
    {
        if (!Str::startsWith($data['mime_type'] ?? '', 'image/')) {
            return;
        }

        $metadata = $data['metadata'] ?? [];

        if (empty($metadata)) {
            return;
        }

        if (empty($metadata['_wp_attachment_metadata']) && empty($metadata['_wp_attached_file'])) {
            return;
        }

        $meta = [];

        $serialized = Arr::first($metadata['_wp_attachment_metadata'] ?? []);

        if (is_not_empty_string($serialized)) {
            try {
                /** @var TAttachmentMetadata $meta */
                $meta = unserialize($serialized);

                if (!empty($meta['sizes'])) {
                    $max = $meta['width'] ?? 0;

                    foreach ($meta['sizes'] as $size) {
                        if ($size['width'] <= $max) {
                            continue;
                        }

                        $max = $size['width'];

                        $meta['width'] = $size['width'];

                        $meta['height'] = $size['height'];

                        $meta['file'] = sprintf(
                            '%s/%s',
                            Str::beforeLast($meta['file'], '/'),
                            $size['file'],
                        );
                    }
                }
            } catch (Throwable) {
                //
            }
        }

        $path = $meta['file'] ?? Arr::first($metadata['_wp_attached_file'] ?? []);

        if (!is_not_empty_string($path)) {
            return;
        }

        $alt = Arr::first($metadata['_wp_attachment_image_alt'] ?? []);

        $this->mapping['attachments'][$data['id']] = [
            'path' => $path,
            'alt' => is_not_empty_string($alt) ? $alt : null,
            'caption' => $data['excerpt'] ?: null,
        ];
    }

    protected function pullCovers(): void
    {
        foreach ($this->mapping['covers'] as $attachmentId => $articleIds) {
            $data = $this->mapping['attachments'][$attachmentId] ?? null;

            if ($data === null || empty($data['path'])) {
                continue;
            }

            $articles = Article::whereIn('id', $articleIds)->get();

            foreach ($articles as $article) {
                $url = $this->fetch($data['path'], $article->id, 'hero-photo');

                $article->updateQuietly([
                    'cover' => [
                        'url' => $url,
                        'alt' => $data['alt'],
                        'caption' => $data['caption'],
                    ],
                ]);
            }
        }
    }

    /**
     * Fetch external resource.
     */
    protected function fetch(string $url, int $articleId, string $collection): string
    {
        if (Str::startsWith($url, 'data:image/')) {
            return $url;
        }

        if (Str::contains($url, '//images.unsplash.com')) {
            return $url;
        }

        $ads = [
            '.amazon-adsystem.com/',
        ];

        if (Str::contains($url, $ads, true)) {
            return $url;
        }

        $url = str_replace(
            [
                'https://madalbal.bg/wp-content/uploads',
                'http://joy.bg/sabg/wp-content/uploads',
            ],
            'https://blog.madalbal.bg/wp-content/uploads',
            $url,
        );

        try {
            $temp = temp_file();

            $token = unique_token();

            $this->http->withOptions(['sink' => $temp])->get($url)->throw();

            $mime = mime_content_type($temp);

            if (empty($mime) || !Str::startsWith($mime, 'image/')) {
                return $url;
            }

            $dimensions = getimagesize($temp);

            if ($dimensions !== false) {
                [$width, $height] = $dimensions;
            }

            $name = Str::afterLast($url, '/');

            $media = Media::create([
                'token' => unique_token(),
                'tenant_id' => $this->tenant->id,
                'model_type' => Article::class,
                'model_id' => $articleId,
                'collection' => $collection,
                'path' => $this->upload(new UploadedFile($temp, $name), $token),
                'mime' => $mime,
                'size' => filesize($temp),
                'width' => $width ?? 0,
                'height' => $height ?? 0,
                'blurhash' => null,
            ]);

            return $media->url;
        } catch (RequestException $e) {
            if (!$e->response->notFound() && !$e->response->forbidden()) {
                Log::channel('slack')->error('Unable to download the image.', [
                    'tenant' => $this->tenant->id,
                    'domain' => $this->host,
                    'url' => $url,
                    'code' => $e->getCode(),
                    'message' => $e->getMessage(),
                ]);
            }
        } catch (ConnectionException $e) {
            $ignored = [
                'Could not resolve host',
                'handshake failure',
                'Operation timed out after',
                'Resolving timed out after',
            ];

            if (!Str::contains($e, $ignored, true)) {
                withScope(function (Scope $scope) use ($e, $url) {
                    $scope->setContext('image', [
                        'domain' => $this->host,
                        'url' => $url,
                    ]);

                    captureException($e);
                });
            }
        } catch (TooManyRedirectsException $e) {
            // ignore
        } catch (Throwable $e) {
            captureException($e);
        } finally {
            if (isset($temp)) {
                @unlink($temp);
            }
        }

        return $url;
    }

    /**
     * Upload file to AWS S3.
     */
    protected function upload(UploadedFile $file, string $token): string
    {
        $path = $this->path($file->extension(), $token);

        Storage::cloud()->putFileAs(dirname($path), $file, basename($path));

        return $path;
    }

    /**
     * Get store path.
     */
    protected function path(string $extension, string $token): string
    {
        $chunks = [
            'assets',
            $this->tenant->id,
            'migrations',
            $this->now->timestamp,
            $token,
        ];

        $path = implode('/', $chunks);

        return sprintf('%s.%s', $path, $extension);
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    protected function rudderstack(string $event, array $properties = []): void
    {
        Segment::track([
            'userId' => (string) $this->tenant->owner->id,
            'event' => $event,
            'properties' => array_merge($properties, [
                'tenant_uid' => $this->tenant->id,
                'tenant_name' => $this->tenant->name,
                'wordpress_url' => $this->host ?? null,
                'imported_users' => count($this->mapping['users']),
                'imported_articles' => count($this->mapping['posts']),
                'imported_categories' => count($this->mapping['categories']),
                'imported_tags' => count($this->mapping['tags']),
                'imported_attachments' => count($this->mapping['attachments']),
            ]),
            'context' => [
                'groupId' => $this->tenant->id,
            ],
        ]);
    }
}
