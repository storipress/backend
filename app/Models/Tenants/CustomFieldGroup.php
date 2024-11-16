<?php

namespace App\Models\Tenants;

use App\Enums\CustomField\GroupType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * App\Models\Tenants\CustomFieldGroup
 *
 * @property int $id
 * @property string $key
 * @property \BenSampo\Enum\Enum $type
 * @property string $name
 * @property string|null $description
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Tenants\CustomField> $customFields
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Tenants\Desk> $desks
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Tenants\Tag> $tags
 *
 * @method static \Database\Factories\Tenants\CustomFieldGroupFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|CustomFieldGroup newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|CustomFieldGroup newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|CustomFieldGroup onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|CustomFieldGroup query()
 * @method static \Illuminate\Database\Eloquent\Builder|CustomFieldGroup withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|CustomFieldGroup withoutTrashed()
 *
 * @mixin \Eloquent
 */
class CustomFieldGroup extends Entity
{
    use HasFactory;
    use SoftDeletes;

    /**
     * The attributes that should be cast.
     *
     * @var array<string, class-string|string>
     */
    protected $casts = [
        'type' => GroupType::class,
    ];

    /**
     * @return HasMany<CustomField>
     */
    public function customFields(): HasMany
    {
        return $this->hasMany(CustomField::class);
    }

    /**
     * @return MorphToMany<Tag>
     */
    public function tags(): MorphToMany
    {
        return $this->morphedByMany(
            Tag::class,
            'custom_field_groupable',
            'custom_field_groupable',
        );
    }

    /**
     * @return MorphToMany<Desk>
     */
    public function desks(): MorphToMany
    {
        return $this->morphedByMany(
            Desk::class,
            'custom_field_groupable',
            'custom_field_groupable',
        );
    }
}
