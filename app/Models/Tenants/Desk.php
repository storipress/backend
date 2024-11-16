<?php

namespace App\Models\Tenants;

use App\Enums\CustomField\GroupType;
use App\Models\Attributes\HasCustomFields;
use App\Models\Attributes\StringIdentify;
use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Rutorika\Sortable\SortableTrait;

/**
 * App\Models\Tenants\Desk
 *
 * @property int $id
 * @property int|null $wordpress_id
 * @property int|null $shopify_id
 * @property string|null $webflow_id
 * @property int|null $desk_id
 * @property int|null $layout_id
 * @property bool $open_access
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property array|null $seo
 * @property int $order
 * @property int $draft_articles_count
 * @property int $published_articles_count
 * @property int $total_articles_count
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read Collection<int, \App\Models\Tenants\Article> $articles
 * @property-read Desk|null $desk
 * @property-read Collection<int, Desk> $desks
 * @property-read Collection<int, \App\Models\Tenants\User> $editors
 * @property-read \Illuminate\Database\Eloquent\Collection<int, CustomField> $metafields
 * @property-read string $sid
 * @property-read Collection<int, \App\Models\Tenants\CustomFieldGroup> $groupable
 * @property-read \App\Models\Tenants\Layout|null $layout
 * @property-read Collection<int, \App\Models\Tenants\User> $users
 * @property-read Collection<int, \App\Models\Tenants\User> $writers
 *
 * @method static \Database\Factories\Tenants\DeskFactory factory($count = null, $state = [])
 * @method static Builder|Desk findSimilarSlugs(string $attribute, array $config, string $slug)
 * @method static Builder|Desk newModelQuery()
 * @method static Builder|Desk newQuery()
 * @method static Builder|Desk onlyTrashed()
 * @method static Builder|Desk query()
 * @method static Builder|Desk root()
 * @method static Builder|Desk sid(string $sid)
 * @method static Builder|Desk sorted()
 * @method static Builder|Desk withTrashed()
 * @method static Builder|Desk withUniqueSlugConstraints(\Illuminate\Database\Eloquent\Model $model, string $attribute, array $config, string $slug)
 * @method static Builder|Desk withoutTrashed()
 *
 * @mixin \Eloquent
 */
class Desk extends Entity
{
    use HasCustomFields;
    use HasFactory;
    use Sluggable;
    use SoftDeletes;
    use SortableTrait;
    use StringIdentify;

    /**
     * Sortable group field.
     *
     * @var array<int, string>
     */
    protected static $sortableGroupField = ['desk_id'];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'open_access' => 'bool',
        'seo' => 'array',
        'order' => 'int',
    ];

    /**
     * The minimum sid length.
     *
     * @var int
     */
    protected $minSidLength = 4;

    /**
     * @return BelongsTo<Desk, Desk>
     */
    public function desk(): BelongsTo
    {
        return $this->belongsTo(Desk::class);
    }

    /**
     * @return HasMany<Desk>
     */
    public function desks(): HasMany
    {
        return $this->hasMany(Desk::class);
    }

    /**
     * @return BelongsTo<Layout, Desk>
     */
    public function layout(): BelongsTo
    {
        return $this->belongsTo(Layout::class);
    }

    /**
     * @return BelongsToMany<User>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    /**
     * @return BelongsToMany<User>
     */
    public function editors(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->whereNotIn('role', ['contributor', 'author']);
    }

    /**
     * @return BelongsToMany<User>
     */
    public function writers(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->whereIn('role', ['contributor', 'author']);
    }

    /**
     * @return HasMany<Article>
     */
    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    /**
     * @return MorphToMany<CustomFieldGroup>
     */
    public function groupable(): MorphToMany
    {
        return $this->morphToMany(
            CustomFieldGroup::class,
            'custom_field_groupable',
            'custom_field_groupable',
        )
            ->where('type', '=', GroupType::deskMetafield());
    }

    /**
     * @param  Builder<Desk>  $query
     * @return Builder<Desk>
     */
    public function scopeRoot(Builder $query): Builder
    {
        return $query->whereNull('desk_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, CustomField>
     */
    public function getMetafieldsAttribute(): Collection
    {
        return $this->getGroupableCustomFields();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, CustomField>
     */
    public function getCustomFieldsAttribute(): Collection
    {
        /** @var Collection<int, CustomField> */
        return (new Collection())
            ->merge($this->metafields)
            ->keyBy('id');
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
                'source' => 'name',
                'includeTrashed' => true,
                'maxLength' => 250,
            ],
        ];
    }
}
