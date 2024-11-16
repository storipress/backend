<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * App\Models\SpamEmail
 *
 * @property int $id
 * @property string $email
 * @property int $times
 * @property array|null $records
 * @property \Illuminate\Support\Carbon $expired_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read int $ban_days
 *
 * @method static \Database\Factories\SpamEmailFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|SpamEmail newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|SpamEmail newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|SpamEmail query()
 *
 * @mixin \Eloquent
 */
class SpamEmail extends Entity
{
    use HasFactory;

    /**
     * The attributes that should be cast.
     *
     * @var array<string, class-string|string>
     */
    protected $casts = [
        'records' => 'array',
        'expired_at' => 'datetime',
    ];

    public function getBanDaysAttribute(): int
    {
        return match ($this->times) {
            0 => 1,
            1 => 3,
            2 => 7,
            3 => 30,
            4 => 90,
            default => 365,
        };
    }
}
