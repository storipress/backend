<?php

namespace App\Models\Tenants;

use App\Enums\CustomField\Type;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * App\Models\Tenants\CustomFieldValue
 *
 * @property int $id
 * @property int $custom_field_id
 * @property string $custom_field_morph_id
 * @property string $custom_field_morph_type
 * @property \BenSampo\Enum\Enum|null $type
 * @property mixed|null $value
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read \App\Models\Tenants\CustomField|null $customField
 *
 * @method static \Database\Factories\Tenants\CustomFieldValueFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|CustomFieldValue newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|CustomFieldValue newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|CustomFieldValue onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|CustomFieldValue query()
 * @method static \Illuminate\Database\Eloquent\Builder|CustomFieldValue withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|CustomFieldValue withoutTrashed()
 *
 * @mixin \Eloquent
 */
class CustomFieldValue extends Entity
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
        'value' => 'array',
    ];

    /**
     * @return BelongsTo<CustomField, CustomFieldValue>
     */
    public function customField(): BelongsTo
    {
        return $this->belongsTo(CustomField::class);
    }

    public function getValueAttribute(mixed $value): mixed
    {
        $result = $this->fromJson($value); // @phpstan-ignore-line

        if (Type::date()->is($this->type)) {
            return Carbon::parse($result); // @phpstan-ignore-line
        }

        if (Type::reference()->is($this->type)) {
            $value = is_string($value) ? json_decode($value) : $value;

            if (!is_array($value) || empty($value)) {
                return [];
            }

            $model = $this->customField?->options['target'] ?? null;

            if (is_a($model, WebflowReference::class, true)) {
                return array_map(fn ($val) => new WebflowReference(['id' => $val]), $value);
            }

            if (!is_a($model, Model::class, true)) {
                return [];
            }

            return (new $model())->whereIn('id', $value)->get();
        }

        return $result;
    }
}
