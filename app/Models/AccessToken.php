<?php

namespace App\Models;

use App\Enums\AccessToken\Type;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;
use Webmozart\Assert\Assert;

/**
 * App\Models\AccessToken
 *
 * @property int $id
 * @property string $name
 * @property string $token
 * @property array|null $abilities
 * @property string $ip
 * @property string|null $user_agent
 * @property array|null $data
 * @property \Illuminate\Support\Carbon|null $last_used_at
 * @property \Illuminate\Support\Carbon $expires_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\AccessTokenActivity> $activities
 * @property-read \Illuminate\Database\Eloquent\Model|\Eloquent $tokenable
 *
 * @method static \Database\Factories\AccessTokenFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|AccessToken newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|AccessToken newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|AccessToken query()
 *
 * @property string $tokenable_type
 * @property string $tokenable_id
 *
 * @mixin \Eloquent
 */
class AccessToken extends Entity
{
    use HasFactory;

    /**
     * The attributes that should be cast.
     *
     * @var array<string, class-string|string>
     */
    protected $casts = [
        'abilities' => 'json',
        'data' => 'json',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * Get the tokenable model that the access token belongs to.
     *
     * @return MorphTo<\Illuminate\Database\Eloquent\Model, AccessToken>
     */
    public function tokenable(): MorphTo
    {
        return $this->morphTo('tokenable');
    }

    /**
     * @return HasMany<AccessTokenActivity>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(AccessTokenActivity::class);
    }

    /**
     * Generate a 36 bytes random token with crc32 checksum.
     */
    public static function token(Type $type): string
    {
        $token = sprintf('%s_%s', $type->value, Str::random(36));

        $checksum = base62_crc32($token, 6, '0');

        $result = $token . $checksum;

        Assert::length($result, 46, $result . ' is not a valid token.');

        return $result;
    }
}
