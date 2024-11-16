<?php

namespace App\Models\Tenants;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\Tenants\UserActivity
 *
 * @property int $id
 * @property int $user_id
 * @property string $role
 * @property string $name
 * @property string|null $subject_type
 * @property string|null $subject_id
 * @property array|null $data
 * @property string $ip
 * @property string $user_agent
 * @property \Illuminate\Support\Carbon $occurred_at
 * @property-read \App\Models\Tenants\User $user
 *
 * @method static \Database\Factories\Tenants\UserActivityFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|UserActivity newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|UserActivity newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|UserActivity query()
 *
 * @mixin \Eloquent
 */
class UserActivity extends Entity
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
     * @var array<string, string>
     */
    protected $casts = [
        'data' => 'array',
        'occurred_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, UserActivity>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Log a user activity.
     *
     * @param  array<mixed>|null  $data
     */
    public static function log(
        string $name,
        ?Model $subject = null,
        ?array $data = null,
        ?int $userId = null,
    ): ?UserActivity {
        $user = User::find($userId ?: auth()->id());

        if ($user === null) {
            return null;
        }

        return self::create([
            'name' => $name,
            'user_id' => $user->getKey(),
            'role' => $user->role,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject?->getKey(),
            'data' => $data,
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
