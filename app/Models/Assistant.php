<?php

namespace App\Models;

use App\Enums\Assistant\Model;
use App\Enums\Assistant\Type;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\Assistant
 *
 * @property int $id
 * @property string $uuid
 * @property string $chat_id
 * @property string $tenant_id
 * @property int $user_id
 * @property \BenSampo\Enum\Enum $model
 * @property \BenSampo\Enum\Enum $type
 * @property array $data
 * @property \Illuminate\Support\Carbon $occurred_at
 * @property-read \App\Models\Tenant|null $tenant
 * @property-read \App\Models\User|null $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder|Assistant newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Assistant newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Assistant query()
 *
 * @mixin \Eloquent
 */
class Assistant extends Entity
{
    use HasFactory;

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that should be cast.
     *
     * @var array<string, class-string|string>
     */
    protected $casts = [
        'model' => Model::class,
        'type' => Type::class,
        'data' => 'array',
        'occurred_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Tenant, Assistant>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<User, Assistant>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
