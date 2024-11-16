<?php

namespace App\Models\Tenants;

use App\Enums\CustomField\Type;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * App\Models\Tenants\CustomField
 *
 * @property int $id
 * @property int $custom_field_group_id
 * @property string $key
 * @property \BenSampo\Enum\Enum $type
 * @property string $name
 * @property string|null $description
 * @property array $options
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\Tenants\CustomFieldGroup|null $group
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Tenants\CustomFieldValue> $values
 *
 * @method static \Database\Factories\Tenants\CustomFieldFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|CustomField newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|CustomField newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|CustomField onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|CustomField query()
 * @method static \Illuminate\Database\Eloquent\Builder|CustomField withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|CustomField withoutTrashed()
 *
 * @mixin \Eloquent
 */
class CustomField extends Entity
{
    use HasFactory;
    use SoftDeletes;

    /**
     * The attributes that should be cast.
     *
     * @var array<string, class-string|string>
     */
    protected $casts = [
        'type' => Type::class,
        'options' => 'array',
    ];

    /**
     * @return BelongsTo<CustomFieldGroup, CustomField>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(CustomFieldGroup::class, 'custom_field_group_id');
    }

    /**
     * @return HasMany<CustomFieldValue>
     */
    public function values(): HasMany
    {
        return $this->hasMany(CustomFieldValue::class);
    }
}
