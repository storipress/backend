<?php

namespace App\Models\Tenants;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * App\Models\Tenants\Invitation
 *
 * @property int $id
 * @property int $inviter_id
 * @property string $email
 * @property int $role_id
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Tenants\Desk> $desks
 * @property-read string $role
 * @property-read \App\Models\Tenants\User|null $inviter
 *
 * @method static \Database\Factories\Tenants\InvitationFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|Invitation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Invitation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Invitation onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|Invitation query()
 * @method static \Illuminate\Database\Eloquent\Builder|Invitation withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|Invitation withoutTrashed()
 *
 * @mixin \Eloquent
 */
class Invitation extends Entity
{
    use HasFactory;
    use SoftDeletes;

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'created_at' => 'datetime',
    ];

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * @return BelongsTo<User, Invitation>
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'inviter_id',
        );
    }

    /**
     * @return BelongsToMany<Desk>
     */
    public function desks(): BelongsToMany
    {
        return $this->belongsToMany(
            Desk::class,
            'invitation_desk',
        );
    }

    public function getRoleAttribute(): string
    {
        return find_role($this->role_id)->name;
    }
}
