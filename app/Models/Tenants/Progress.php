<?php

namespace App\Models\Tenants;

use App\Enums\Progress\ProgressState;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * App\Models\Tenants\Progress
 *
 * @property int $id
 * @property string $name
 * @property \BenSampo\Enum\Enum $state
 * @property string|null $message
 * @property array|null $data
 * @property int|null $progress_id
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Progress> $children
 * @property-read Progress|null $parent
 *
 * @method static \Database\Factories\Tenants\ProgressFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|Progress newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Progress newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Progress query()
 *
 * @mixin \Eloquent
 */
class Progress extends Entity
{
    use HasFactory;

    /**
     * The attributes that should be cast.
     *
     * @var array<string, class-string|string>
     */
    protected $casts = [
        'state' => ProgressState::class,
        'data' => 'array',
    ];

    /**
     * @return BelongsTo<Progress, Progress>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Progress::class, 'progress_id');
    }

    /**
     * @return HasMany<Progress>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Progress::class, 'progress_id');
    }
}
