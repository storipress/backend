<?php

namespace App\Models\Tenants;

use App\Enums\CustomField\GroupType;
use App\Models\Attributes\HasCustomFields;
use App\Models\Attributes\StringIdentify;
use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * App\Models\Tenants\Tag
 *
 * @property int $id
 * @property int|null $wordpress_id
 * @property string|null $webflow_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property int $count
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read Collection<int, \App\Models\Tenants\Article> $articles
 * @property-read \Illuminate\Database\Eloquent\Collection<int, CustomField> $metafields
 * @property-read string $sid
 * @property-read Collection<int, \App\Models\Tenants\CustomFieldGroup> $groupable
 *
 * @method static \Database\Factories\Tenants\TagFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|Tag findSimilarSlugs(string $attribute, array $config, string $slug)
 * @method static \Illuminate\Database\Eloquent\Builder|Tag newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Tag newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Tag onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|Tag query()
 * @method static \Illuminate\Database\Eloquent\Builder|Tag sid(string $sid)
 * @method static \Illuminate\Database\Eloquent\Builder|Tag withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|Tag withUniqueSlugConstraints(\Illuminate\Database\Eloquent\Model $model, string $attribute, array $config, string $slug)
 * @method static \Illuminate\Database\Eloquent\Builder|Tag withoutTrashed()
 *
 * @mixin \Eloquent
 */
class Tag extends Entity
{
    use HasCustomFields;
    use HasFactory;
    use Sluggable;
    use SoftDeletes;
    use StringIdentify;

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'count' => 'int',
    ];

    /**
     * The minimum sid length.
     *
     * @var int
     */
    protected $minSidLength = 5;

    /**
     * @return BelongsToMany<Article>
     */
    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(Article::class);
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
            ->where('type', '=', GroupType::tagMetafield());
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
                'onUpdate' => true,
                'includeTrashed' => true,
                'maxLength' => 250,
            ],
        ];
    }
}
