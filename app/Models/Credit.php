<?php

namespace App\Models;

use App\Enums\Credit\State;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\Credit
 *
 * @property int $id
 * @property int $user_id
 * @property int $amount
 * @property \BenSampo\Enum\Enum $state
 * @property string $earned_from
 * @property string|null $invoice_id
 * @property array|null $data
 * @property \Illuminate\Support\Carbon|null $used_at
 * @property \Illuminate\Support\Carbon|null $earned_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read bool $used
 * @property-read \App\Models\User $user
 *
 * @method static \Database\Factories\CreditFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|Credit newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Credit newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Credit query()
 *
 * @mixin \Eloquent
 */
class Credit extends Entity
{
    use HasFactory;

    /**
     * The attributes that should be cast.
     *
     * @var array<string, class-string|string>
     */
    protected $casts = [
        'state' => State::class,
        'data' => 'array',
        'used_at' => 'datetime',
        'earned_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, Credit>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getUsedAttribute(): bool
    {
        return $this->used_at !== null;
    }
}
