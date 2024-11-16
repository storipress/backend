<?php

namespace App\Models;

use App\Enums\User\Status;

/**
 * App\Models\UserStatus
 *
 * @property int $id
 * @property string $tenant_id
 * @property int $user_id
 * @property \BenSampo\Enum\Enum $status
 * @property bool $hidden
 * @property string|null $role
 *
 * @method static \Illuminate\Database\Eloquent\Builder|UserStatus newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|UserStatus newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|UserStatus query()
 *
 * @property-read bool $suspended
 *
 * @mixin \Eloquent
 */
class UserStatus extends Pivot
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'tenant_user';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = true;

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string|class-string>
     */
    protected $casts = [
        'status' => Status::class,
        'hidden' => 'bool',
    ];

    /**
     * Whether the user is suspended or not on target tenant.
     */
    public function getSuspendedAttribute(): bool
    {
        return Status::suspended()->is($this->status);
    }
}
