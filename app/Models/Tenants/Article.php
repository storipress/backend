<?php

namespace App\Models\Tenants;

use App\Enums\Article\Plan;
use App\Enums\Article\PublishType;
use App\Enums\CustomField\GroupType;
use App\Models\Attributes\HasCustomFields;
use App\Models\Attributes\StringIdentify;
use App\Models\Tenant;
use App\Models\Tenants\Integrations\Webflow;
use App\Models\Tenants\Integrations\WordPress;
use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Laravel\Scout\Searchable;
use Rutorika\Sortable\SortableTrait;
use Typesense\LaravelTypesense\Interfaces\TypesenseDocument;
use Webmozart\Assert\Assert;

/**
 * App\Models\Tenants\Article
 *
 * @phpstan-type TFacebook array{
 *     text: string,
 *     enable: bool,
 *     page_id: string,
 *     scheduled_at: string,
 * }
 * @phpstan-type TTwitter array{
 *     text: string,
 *     enable: bool,
 *     user_id: string,
 *     scheduled_at: string,
 * }
 * @phpstan-type TLinkedIn array{
 *     text: string,
 *     enable: bool,
 *     author_id: string,
 *     scheduled_at: string,
 * }
 * @phpstan-type TAutoPosting array{
 *     enable: bool,
 *     text: string,
 * }
 *
 * @property array{
 *     og: array{
 *          title: string,
 *          description: string,
 *     },
 *     meta: array{
 *          title: string,
 *          description: string,
 *     },
 *     ogImage: string,
 *     hasSlug: boolean,
 *  }|null $seo
 * @property array{
 *     twitter?: TAutoPosting,
 *     facebook?: TAutoPosting,
 *     linkedin?: TAutoPosting,
 * }|null $auto_posting
 *
 * @meta static void searchable()
 * @meta static void unsearchable()
 *
 * @property int $id
 * @property string|null $webflow_id
 * @property int|null $wordpress_id
 * @property int $desk_id
 * @property int|null $layout_id
 * @property int $stage_id
 * @property array|null $shadow_authors
 * @property string $title
 * @property string $slug
 * @property array|null $pathnames
 * @property string|null $blurb
 * @property int $order
 * @property bool $featured
 * @property \BenSampo\Enum\Enum $publish_type
 * @property array<string, array<mixed>> $document
 * @property string|null $html
 * @property string|null $plaintext
 * @property array{
 *     alt: string,
 *     caption?: string,
 *     url: string,
 *     crop?: mixed,
 *     wordpress?: array{
 *         id: int,
 *         alt: string,
 *         caption: string,
 *         url: string,
 *     },
 *     wordpress_id?: int,
 * }|null $cover
 * @property \BenSampo\Enum\Enum $plan
 * @property bool $newsletter
 * @property \Illuminate\Support\Carbon|null $newsletter_at
 * @property string|null $encryption_key
 * @property \Illuminate\Support\Carbon|null $published_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read Collection<int, \App\Models\Tenants\User> $authors
 * @property-read Collection<int, \App\Models\Tenants\ArticleAutoPosting> $autoPostings
 * @property-read \App\Models\Tenants\Desk $desk
 * @property-read \Illuminate\Database\Eloquent\Collection<int, CustomField> $content_blocks
 * @property-read \Illuminate\Database\Eloquent\Collection<int, CustomField> $custom_fields
 * @property-read bool $draft
 * @property-read string $edit_url
 * @property-read array{}|\App\Models\Tenants\TFacebook $facebook
 * @property-read array{}|\App\Models\Tenants\TLinkedIn $linked_in
 * @property-read \Illuminate\Database\Eloquent\Collection<int, CustomField> $metafields
 * @property-read bool $published
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Article> $relevances
 * @property-read bool $scheduled
 * @property-read string $sid
 * @property-read \App\Models\Tenants\Stage $stage
 * @property-read array{}|\App\Models\Tenants\TTwitter $twitter
 * @property-read string $url
 * @property-read Collection<int, \App\Models\Tenants\Image> $images
 * @property-read \App\Models\Tenants\Layout|null $layout
 * @property-read Collection<int, Article> $leftRelevances
 * @property-read Collection<int, \App\Models\Tenants\AiAnalysis> $pain_point
 * @property-read Collection<int, Article> $rightRelevances
 * @property-read Collection<int, \App\Models\Tenants\Tag> $tags
 * @property-read Collection<int, \App\Models\Tenants\ArticleThread> $threads
 *
 * @method static \Database\Factories\Tenants\ArticleFactory factory($count = null, $state = [])
 * @method static Builder|Article findSimilarSlugs(string $attribute, array $config, string $slug)
 * @method static Builder|Article newModelQuery()
 * @method static Builder|Article newQuery()
 * @method static Builder|Article onlyTrashed()
 * @method static Builder|Article published(bool $value)
 * @method static Builder|Article query()
 * @method static Builder|Article sid(string $sid)
 * @method static Builder|Article sorted()
 * @method static Builder|Article unscheduled(bool $value)
 * @method static Builder|Article withTrashed()
 * @method static Builder|Article withUniqueSlugConstraints(\Illuminate\Database\Eloquent\Model $model, string $attribute, array $config, string $slug)
 * @method static Builder|Article withoutTrashed()
 *
 * @mixin \Eloquent
 */
class Article extends Entity implements TypesenseDocument
{
    use HasCustomFields;
    use HasFactory;
    use Searchable;
    use Sluggable;
    use SoftDeletes;
    use SortableTrait;
    use StringIdentify;

    /**
     * Sortable group field.
     *
     * @var array<int, string>
     */
    protected static $sortableGroupField = ['stage_id'];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, class-string|string>
     */
    protected $casts = [
        'shadow_authors' => 'array',
        'pathnames' => 'array',
        'order' => 'int',
        'featured' => 'bool',
        'document' => 'array',
        'cover' => 'array',
        'seo' => 'array',
        'auto_posting' => 'array',
        'plan' => Plan::class,
        'newsletter' => 'bool',
        'newsletter_at' => 'datetime',
        'published_at' => 'datetime',
        'publish_type' => PublishType::class,
    ];

    /**
     * The relations to eager load on every query.
     *
     * @var array<int, string>
     */
    protected $with = [
        'stage',
    ];

    /**
     * @var array<string, Collection<int, CustomField>>
     */
    protected array $fields = [];

    /**
     * @return BelongsTo<Desk, Article>
     */
    public function desk(): BelongsTo
    {
        return $this->belongsTo(Desk::class);
    }

    /**
     * @return BelongsTo<Layout, Article>
     */
    public function layout(): BelongsTo
    {
        return $this->belongsTo(Layout::class);
    }

    /**
     * @return BelongsTo<Stage, Article>
     */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }

    /**
     * @return BelongsToMany<User>
     */
    public function authors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'article_author');
    }

    /**
     * @return BelongsToMany<Tag>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)
            ->as('article_tag_pivot');
    }

    /**
     * @return HasMany<ArticleAutoPosting>
     */
    public function autoPostings(): HasMany
    {
        return $this->hasMany(ArticleAutoPosting::class);
    }

    /**
     * @return HasMany<ArticleThread>
     */
    public function threads(): HasMany
    {
        return $this->hasMany(ArticleThread::class);
    }

    /**
     * @return MorphMany<Image>
     */
    public function images(): MorphMany
    {
        return $this->morphMany(
            Image::class,
            'imageable',
        );
    }

    /**
     * @return MorphMany<AiAnalysis>
     */
    public function pain_point(): MorphMany
    {
        return $this->morphMany(
            AiAnalysis::class,
            'target',
        );
    }

    /**
     * @return BelongsToMany<Article>
     */
    public function leftRelevances(): BelongsToMany
    {
        return $this->belongsToMany(
            Article::class,
            'article_correlation',
            'source_id',
            'target_id',
        )
            ->withPivot('correlation')
            ->withPivot('updated_at')
            ->published(true)
            ->orderByDesc('correlation')
            ->take(10);
    }

    /**
     * @return BelongsToMany<Article>
     */
    public function rightRelevances(): BelongsToMany
    {
        return $this->belongsToMany(
            Article::class,
            'article_correlation',
            'target_id',
            'source_id',
        )
            ->withPivot('correlation')
            ->withPivot('updated_at')
            ->published(true)
            ->orderByDesc('correlation')
            ->take(10);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Article>
     */
    public function getRelevancesAttribute(): Collection
    {
        /** @var Collection<int, Article> $items */
        $items = new Collection();

        $items->push(...$this->leftRelevances);

        $items->push(...$this->rightRelevances);

        return $items->unique('id')
            ->sortByDesc('pivot.correlation');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, CustomField>
     */
    public function getMetafieldsAttribute(): Collection
    {
        if (isset($this->fields['metafields'])) {
            return $this->fields['metafields'];
        }

        return $this->fields['metafields'] = $this->getCustomFields(GroupType::articleMetafield());
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, CustomField>
     */
    public function getContentBlocksAttribute(): Collection
    {
        if (isset($this->fields['content_blocks'])) {
            return $this->fields['content_blocks'];
        }

        return $this->fields['content_blocks'] = $this->getCustomFields(GroupType::articleContentBlock());
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, CustomField>
     */
    public function getCustomFieldsAttribute(): Collection
    {
        /** @var Collection<int, CustomField> */
        return (new Collection())
            ->merge($this->metafields)
            ->merge($this->content_blocks)
            ->keyBy('id');
    }

    /**
     * Scope a query to only include popular users.
     *
     * @param  Builder<Article>  $query
     * @return Builder<Article>
     */
    public function scopeUnscheduled(Builder $query, bool $value): Builder
    {
        if (! $value) {
            return $query;
        }

        return $query->whereNull('published_at');
    }

    /**
     * Scope a query to only include published articles.
     *
     * @param  Builder<Article>  $query
     * @return Builder<Article>
     */
    public function scopePublished(Builder $query, bool $value): Builder
    {
        if (! $value) {
            return $query;
        }

        $stageId = Stage::withoutEagerLoads()->ready()->sole(['id'])->id;

        return $query
            ->where('stage_id', '=', $stageId)
            ->where('published_at', '<=', now());
    }

    /**
     * Get article document attribute.
     *
     * @return array<string, array<mixed>>
     */
    public function getDocumentAttribute(): array
    {
        $origin = $this->attributes['document'] ?? null;

        if (! empty($origin)) {
            /** @var array<string, array<mixed>> $data */
            $data = json_decode($origin, true);

            return $data;
        }

        $emptyDoc = [
            'type' => 'doc',
            'content' => [],
        ];

        return [
            'default' => $emptyDoc,
            'title' => app('prosemirror')->toProseMirror($this->attributes['title']) ?: $emptyDoc,
            'blurb' => app('prosemirror')->toProseMirror($this->attributes['blurb'] ?: '') ?: $emptyDoc,
            'annotations' => [],
        ];
    }

    public function getStageAttribute(): Stage
    {
        /** @var Stage|null $stage */
        $stage = $this->getRelationValue('stage');

        if ($stage === null) {
            $stage = Stage::where('default', '=', true)
                ->first();
        }

        Assert::isInstanceOf($stage, Stage::class);

        return $stage;
    }

    /**
     * Whether the article is in draft stage or not.
     */
    public function getDraftAttribute(): bool
    {
        return ! $this->stage->ready || ! $this->published_at;
    }

    /**
     * Whether the article is in scheduled stage or not.
     */
    public function getScheduledAttribute(): bool
    {
        return $this->stage->ready &&
               $this->published_at &&
               $this->published_at->isFuture();
    }

    /**
     * Whether the article is in published stage or not.
     */
    public function getPublishedAttribute(): bool
    {
        return $this->stage->ready &&
               $this->published_at &&
               $this->published_at->isPast();
    }

    /**
     * Get static site article url.
     */
    public function getUrlAttribute(): string
    {
        /** @var Tenant $tenant */
        $tenant = tenant();

        $domain = $tenant->url;

        if (! empty($this->webflow_id)) {
            $webflow = Webflow::retrieve();

            if ($webflow->is_activated && ! empty($webflow->config->domain) && ! empty($webflow->config->collections['blog']['slug'])) {
                return sprintf('https://%s/%s/%s', $webflow->config->domain, $webflow->config->collections['blog']['slug'], $this->slug);
            }
        }

        if (Integration::isShopifyActivate()) {
            return $this->getUrl('shopify');
        }

        if (! empty($this->wordpress_id)) {
            $wordpress = WordPress::retrieve();

            if ($wordpress->is_activated && ! empty($wordpress->config->url)) {
                return sprintf('%s/?p=%d', rtrim($wordpress->config->url, '/'), $this->wordpress_id);
            }
        }

        return sprintf('https://%s/posts/%s', $domain, rawurlencode($this->attributes['slug']));
    }

    public function getUrl(string $key): string
    {
        /** @var Collection<int, ArticleAutoPosting> $relation */
        $relation = $this->getRelationValue('autoPostings');

        $post = $relation->firstWhere('platform', '=', $key);

        if ($post === null) {
            return '';
        }

        $path = sprintf('%s/%s/%s', $post->domain, $post->prefix, $post->pathname);

        return Str::of($path)->replaceMatches('#/+#', '/')->prepend('https://')->value();
    }

    public function getEditUrlAttribute(): string
    {
        /** @var Tenant $tenant */
        $tenant = tenant();

        /** @var string $key */
        $key = $tenant->getKey();

        $domain = match (app()->environment()) {
            'local' => 'localhost:3333',
            'development' => 'storipress.dev',
            'staging' => 'storipress.pro',
            default => 'stori.press',
        };

        /** @var string $id */
        $id = $this->attributes['id'];

        return sprintf('https://%s/%s/articles/%s/edit', $domain, $key, $id);
    }

    /**
     * get auto posting twitter data
     *
     * @return array{}|TTwitter
     */
    public function getTwitterAttribute(): array
    {
        $origin = $this->attributes['auto_posting'] ?? null;

        if (empty($origin)) {
            return [];
        }

        /** @var array{ twitter?: TTwitter } $autoPosting */
        $autoPosting = json_decode($origin, true);

        /** @var TTwitter|array{} $twitter */
        $twitter = Arr::get($autoPosting, 'twitter', []);

        return $twitter;
    }

    /**
     * get auto posting facebook data
     *
     * @return array{}|TFacebook
     */
    public function getFacebookAttribute(): array
    {
        $origin = $this->attributes['auto_posting'] ?? null;

        if (empty($origin)) {
            return [];
        }

        /** @var array{ facebook?: TFacebook } $autoPosting */
        $autoPosting = json_decode($origin, true);

        /** @var TFacebook|array{} $facebook */
        $facebook = Arr::get($autoPosting, 'facebook', []);

        return $facebook;
    }

    /**
     * get auto posting LinkedIn data
     *
     * @return array{}|TLinkedIn
     */
    public function getLinkedInAttribute(): array
    {
        $origin = $this->attributes['auto_posting'] ?? null;

        if (empty($origin)) {
            return [];
        }

        /** @var array{ linkedin?: TLinkedIn } $autoPosting */
        $autoPosting = json_decode($origin, true);

        /** @var TLinkedIn|array{} $linkedin */
        $linkedin = Arr::get($autoPosting, 'linkedin', []);

        return $linkedin;
    }

    /**
     * Return the sluggable configuration array for this model.
     *
     * @return array<string, array<string, bool|int|string>>
     */
    public function sluggable(): array
    {
        return [
            'slug' => [
                'source' => 'title',
                'includeTrashed' => true,
                'maxLength' => 250,
            ],
        ];
    }

    /**
     * Get the index name for the model.
     */
    public function searchableAs(): string
    {
        return tenant('id').'-'.$this->getTable();
    }

    /**
     * When updating a model, this method determines if we should update the search index.
     */
    public function searchIndexShouldBeUpdated(): bool
    {
        if ($this->wasRecentlyCreated) {
            return true;
        }

        $attributes = [
            'desk_id',
            'layout_id',
            'stage_id',
            'title',
            'slug',
            'pathnames',
            'blurb',
            'featured',
            'cover',
            'seo',
            'plan',
            'plaintext',
            'order',
            'shadow_authors',
            'published_at',
        ];

        return $this->wasChanged($attributes);
    }

    /**
     * Modify the query used to retrieve models when making all of the models searchable.
     *
     * @param  Builder<Article>  $query
     * @return Builder<Article>
     */
    protected function makeAllSearchableUsing(Builder $query): Builder
    {
        return $query->select([
            'id', 'desk_id', 'layout_id', 'stage_id', 'title', 'slug',
            'pathnames', 'blurb', 'featured', 'cover', 'seo', 'plan',
            'plaintext', 'order', 'shadow_authors', 'published_at',
            'created_at', 'updated_at',
        ]);
    }

    /**
     * Get the indexable data array for the model.
     *
     * @return array<mixed>
     */
    public function toSearchableArray(): array
    {
        $desk = $this->desk->only(['id', 'name', 'slug']);

        $desk['layout'] = $this->desk->layout?->only(['id']);

        $desk['desk'] = $this->desk->desk?->only(['id', 'name', 'slug']);

        if ($desk['desk'] !== null) {
            $desk['desk']['layout'] = $this->desk->desk?->layout?->only(['id']);
        }

        return [
            'id' => (string) $this->id,
            'desk' => $desk,
            'desk_id' => $this->desk_id,
            'desk_name' => $this->desk->name,
            'layout' => $this->layout?->only(['id']),
            'stage' => $this->stage->only(['id', 'name']),
            'stage_id' => $this->stage_id,
            'stage_name' => $this->stage->name,
            'title' => strip_tags($this->title),
            'slug' => $this->slug,
            'pathnames' => array_values($this->pathnames ?: []) ?: null,
            'blurb' => $this->blurb ? strip_tags($this->blurb) : null,
            'featured' => $this->featured,
            'cover' => $this->cover ? json_encode($this->cover) : null,
            'seo' => json_encode($this->seo),
            'plan' => $this->plan->key ?: 'free',
            'content' => $this->plaintext,
            'order' => $this->order,
            'authors' => $this->authors->map->only(['id', 'full_name', 'slug', 'avatar', 'bio', 'socials', 'location'])->map(function ($data) {
                if ($data['socials'] !== null) {
                    $data['socials'] = json_encode($data['socials']);
                }

                return array_filter($data);
            })->toArray(),
            'author_ids' => $this->authors->pluck('id')->map(fn (int|string $id) => strval($id))->toArray(),
            'author_names' => $this->authors->pluck('full_name')->filter()->values()->toArray(),
            'author_avatars' => $this->authors->map->getAttribute('avatar')->filter()->toArray(),
            'shadow_authors' => $this->shadow_authors,
            'tags' => $this->tags->map->only(['id', 'name', 'slug'])->toArray(),
            'tag_ids' => $this->tags->pluck('id')->map(fn (int|string $id) => intval($id))->toArray(), // @phpstan-ignore-line
            'tag_names' => $this->tags->pluck('name')->toArray(),
            'published' => $this->published,
            'published_at' => $this->published_at?->timestamp,
            'created_at' => $this->created_at->timestamp,
            'updated_at' => $this->updated_at->timestamp,
        ];
    }

    /**
     * Typesense search collection schema.
     *
     * @return array{
     *     name: string,
     *     default_sorting_field: string,
     *     enable_nested_fields: bool,
     *     fields: array<int, array{
     *         name: string,
     *         type: string,
     *         facet: bool,
     *         index: bool,
     *         infix: bool,
     *         sort?: bool,
     *         optional?: bool,
     *     }>,
     * }
     */
    public function getCollectionSchema(): array
    {
        return [
            'name' => $this->searchableAs(),
            'default_sorting_field' => 'created_at',
            'enable_nested_fields' => true,
            'fields' => [
                [
                    'name' => 'desk',
                    'type' => 'object',
                    'facet' => false,
                    'index' => true,
                    'infix' => false,
                ],
                [
                    'name' => 'desk_id',
                    'type' => 'int64',
                    'facet' => false,
                    'index' => true,
                    'infix' => false,
                ],
                [
                    'name' => 'desk_name',
                    'type' => 'string',
                    'facet' => false,
                    'index' => true,
                    'infix' => true,
                ],
                [
                    'name' => 'layout',
                    'type' => 'object',
                    'facet' => false,
                    'index' => false,
                    'infix' => false,
                    'optional' => true,
                ],
                [
                    'name' => 'stage',
                    'type' => 'object',
                    'facet' => false,
                    'index' => true,
                    'infix' => false,
                ],
                [
                    'name' => 'stage_id',
                    'type' => 'int64',
                    'facet' => false,
                    'index' => true,
                    'infix' => false,
                ],
                [
                    'name' => 'stage_name',
                    'type' => 'string',
                    'facet' => true,
                    'index' => true,
                    'infix' => false,
                ],
                [
                    'name' => 'title',
                    'type' => 'string',
                    'facet' => false,
                    'index' => true,
                    'infix' => true,
                    'sort' => true,
                ],
                [
                    'name' => 'slug',
                    'type' => 'string',
                    'facet' => false,
                    'index' => true,
                    'infix' => false,
                    'optional' => true,
                ],
                [
                    'name' => 'pathnames',
                    'type' => 'string[]',
                    'facet' => false,
                    'index' => true,
                    'infix' => false,
                    'optional' => true,
                ],
                [
                    'name' => 'blurb',
                    'type' => 'string',
                    'facet' => false,
                    'index' => true,
                    'infix' => true,
                    'optional' => true,
                ],
                [
                    'name' => 'featured',
                    'type' => 'bool',
                    'facet' => false,
                    'index' => true,
                    'infix' => false,
                ],
                [
                    'name' => 'cover',
                    'type' => 'string',
                    'facet' => false,
                    'index' => false,
                    'infix' => false,
                    'optional' => true,
                ],
                [
                    'name' => 'seo',
                    'type' => 'string',
                    'facet' => false,
                    'index' => false,
                    'infix' => false,
                    'optional' => true,
                ],
                [
                    'name' => 'plan',
                    'type' => 'string',
                    'facet' => true,
                    'index' => true,
                    'infix' => false,
                ],
                [
                    'name' => 'content',
                    'type' => 'string',
                    'facet' => false,
                    'index' => true,
                    'infix' => false,
                    'optional' => true,
                ],
                [
                    'name' => 'order',
                    'type' => 'int64',
                    'facet' => false,
                    'index' => true,
                    'infix' => false,
                ],
                [
                    'name' => 'authors',
                    'type' => 'object[]',
                    'facet' => false,
                    'index' => true,
                    'infix' => false,
                    'optional' => true,
                ],
                [
                    'name' => 'authors.id',
                    'type' => 'int64[]',
                    'facet' => false,
                    'index' => false,
                    'infix' => false,
                    'optional' => true,
                ],
                [
                    'name' => 'authors.full_name',
                    'type' => 'string*',
                    'facet' => false,
                    'index' => true,
                    'infix' => true,
                    'optional' => true,
                ],
                [
                    'name' => 'authors.slug',
                    'type' => 'string*',
                    'facet' => false,
                    'index' => false,
                    'infix' => false,
                    'optional' => true,
                ],
                [
                    'name' => 'authors.avatar',
                    'type' => 'string*',
                    'facet' => false,
                    'index' => false,
                    'infix' => false,
                    'optional' => true,
                ],
                [
                    'name' => 'authors.bio',
                    'type' => 'string*',
                    'facet' => false,
                    'index' => true,
                    'infix' => false,
                    'optional' => true,
                ],
                [
                    'name' => 'authors.location',
                    'type' => 'string*',
                    'facet' => false,
                    'index' => false,
                    'infix' => false,
                    'optional' => true,
                ],
                [
                    'name' => 'authors.socials',
                    'type' => 'string*',
                    'facet' => false,
                    'index' => false,
                    'infix' => false,
                    'optional' => true,
                ],
                [
                    'name' => 'author_ids',
                    'type' => 'string[]',
                    'facet' => false,
                    'index' => true,
                    'infix' => false,
                ],
                [
                    'name' => 'author_names',
                    'type' => 'string[]',
                    'facet' => false,
                    'index' => true,
                    'infix' => true,
                ],
                [
                    'name' => 'author_avatars',
                    'type' => 'string[]',
                    'facet' => false,
                    'index' => false,
                    'infix' => false,
                    'optional' => true,
                ],
                [
                    'name' => 'shadow_authors',
                    'type' => 'string[]',
                    'facet' => false,
                    'index' => true,
                    'infix' => true,
                    'optional' => true,
                ],
                [
                    'name' => 'tags',
                    'type' => 'object[]',
                    'facet' => false,
                    'index' => true,
                    'infix' => false,
                    'optional' => true,
                ],
                [
                    'name' => 'tags.id',
                    'type' => 'int64[]',
                    'facet' => false,
                    'index' => false,
                    'infix' => false,
                    'optional' => true,
                ],
                [
                    'name' => 'tags.name',
                    'type' => 'string*',
                    'facet' => false,
                    'index' => true,
                    'infix' => true,
                    'optional' => true,
                ],
                [
                    'name' => 'tags.slug',
                    'type' => 'string*',
                    'facet' => false,
                    'index' => false,
                    'infix' => false,
                    'optional' => true,
                ],
                [
                    'name' => 'tag_ids',
                    'type' => 'int64[]',
                    'facet' => false,
                    'index' => true,
                    'infix' => false,
                ],
                [
                    'name' => 'tag_names',
                    'type' => 'string[]',
                    'facet' => false,
                    'index' => true,
                    'infix' => true,
                ],
                [
                    'name' => 'published',
                    'type' => 'bool',
                    'facet' => false,
                    'index' => true,
                    'infix' => false,
                ],
                [
                    'name' => 'published_at',
                    'type' => 'int64',
                    'facet' => false,
                    'index' => true,
                    'infix' => false,
                    'optional' => true,
                ],
                [
                    'name' => 'created_at',
                    'type' => 'int64',
                    'facet' => false,
                    'index' => true,
                    'infix' => false,
                ],
                [
                    'name' => 'updated_at',
                    'type' => 'int64',
                    'facet' => false,
                    'index' => true,
                    'infix' => false,
                ],
            ],
        ];
    }

    /**
     * Typesense search query by columns.
     *
     * @return string[]
     */
    public function typesenseQueryBy(): array
    {
        return [
            'title',
            'blurb',
            'content',
            'author_names',
            'tag_names',
            'desk_name',
            'stage_name',
        ];
    }

    /**
     * @return string[]
     */
    public function typesenseInfix(): array
    {
        return [
            'fallback',
            'fallback',
            'fallback',
            'fallback',
            'fallback',
            'fallback',
            'fallback',
        ];
    }

    /**
     * Get the data array for the model webhook.
     *
     * @return array<mixed>
     */
    public function toWebhookArray()
    {
        return [
            'id' => $this->id,
            'desk' => $this->desk->only(['id', 'name']),
            'stage' => $this->stage->only(['id', 'name']),
            'title' => strip_tags($this->title),
            'slug' => $this->slug,
            'blurb' => $this->blurb ? strip_tags($this->blurb) : null,
            'featured' => (bool) $this->featured,
            'cover' => $this->cover['url'] ?? null,
            'order' => (int) $this->order,
            'url' => $this->published ? $this->url : null,
            'authors' => $this->authors
                ->map(fn (User $user) => $user->only(['id', 'full_name', 'avatar']))
                ->filter()
                ->values()
                ->toArray(),
            'tags' => $this->tags
                ->map(fn (Tag $tag) => $tag->only(['id', 'name']))
                ->filter()
                ->values()
                ->toArray(),
            'published' => $this->published,
            'published_at' => $this->published_at?->timestamp,
            'created_at' => $this->created_at->timestamp,
            'updated_at' => $this->updated_at->timestamp,
        ];
    }
}
