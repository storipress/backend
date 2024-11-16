<?php

namespace App\Models;

use App\Models\Tenants\UserActivity as TenantUserActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\AccessTokenActivity
 *
 * @property int $id
 * @property int $access_token_id
 * @property string|null $tenant_id
 * @property int|null $user_activity_id
 * @property string $ip
 * @property string|null $user_agent
 * @property \Illuminate\Support\Carbon $occurred_at
 * @property-read \App\Models\AccessToken|null $accessToken
 * @property-read TenantUserActivity|null $tenantUserActivity
 * @property-read \App\Models\UserActivity|null $userActivity
 *
 * @method static \Database\Factories\AccessTokenActivityFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|AccessTokenActivity newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|AccessTokenActivity newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|AccessTokenActivity query()
 *
 * @mixin \Eloquent
 */
class AccessTokenActivity extends Entity
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
        'occurred_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<AccessToken, AccessTokenActivity>
     */
    public function accessToken(): BelongsTo
    {
        return $this->belongsTo(AccessToken::class);
    }

    /**
     * @return BelongsTo<UserActivity, AccessTokenActivity>
     */
    public function userActivity(): BelongsTo
    {
        return $this->belongsTo(UserActivity::class);
    }

    /**
     * @return BelongsTo<TenantUserActivity, AccessTokenActivity>
     */
    public function tenantUserActivity(): BelongsTo
    {
        return $this->belongsTo(TenantUserActivity::class);
    }
}
