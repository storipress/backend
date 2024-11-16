<?php

namespace App\Models\Tenants;

use App\Enums\Release\State;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * App\Models\Tenants\Release
 *
 * @property int $id
 * @property \App\Enums\Release\State $state
 * @property array|null $meta
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Tenants\ReleaseEvent> $events
 * @property-read int $time
 *
 * @method static \Database\Factories\Tenants\ReleaseFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|Release newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Release newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Release query()
 *
 * @mixin \Eloquent
 */
class Release extends Entity
{
    use HasFactory;

    /**
     * The attributes that should be cast.
     *
     * @var array<string, class-string|string>
     */
    protected $casts = [
        'state' => State::class,
        'meta' => 'array',
    ];

    /**
     * @return HasMany<ReleaseEvent>
     */
    public function events(): HasMany
    {
        return $this->hasMany(ReleaseEvent::class);
    }

    public function getTimeAttribute(): int
    {
        return $this->updated_at->diffInSeconds($this->created_at);
    }
}
