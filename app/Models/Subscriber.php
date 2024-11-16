<?php

namespace App\Models;

use App\Authentication\Authenticatable;
use App\Models\Attributes\Avatar;
use App\Models\Attributes\FullName;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Laravel\Cashier\Billable;

/**
 * App\Models\Subscriber
 *
 * @property int $id
 * @property string $email
 * @property bool $bounced
 * @property \Illuminate\Support\Carbon|null $verified_at
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string|null $stripe_id
 * @property string|null $pm_type
 * @property string|null $pm_last_four
 * @property string|null $card_expiration
 * @property array|null $validation
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\AccessToken> $accessTokens
 * @property-read string $avatar
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SubscriberEvent> $events
 * @property-read string|null $name
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Laravel\Cashier\Subscription> $subscriptions
 * @property-read \Stancl\Tenancy\Database\TenantCollection<int, \App\Models\Tenant> $tenants
 *
 * @method static \Database\Factories\SubscriberFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|Subscriber hasExpiredGenericTrial()
 * @method static \Illuminate\Database\Eloquent\Builder|Subscriber newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Subscriber newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Subscriber onGenericTrial()
 * @method static \Illuminate\Database\Eloquent\Builder|Subscriber query()
 *
 * @property-read string|null $full_name
 * @property-read bool $verified
 *
 * @mixin \Eloquent
 */
class Subscriber extends Entity implements AuthenticatableContract, AuthorizableContract
{
    use Authenticatable;
    use Authorizable;
    use Avatar;
    use Billable;
    use FullName;
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
        'bounced' => 'bool',
        'verified_at' => 'datetime',
        'validation' => 'array',
    ];

    /**
     * Subscriber joined tenants.
     *
     * @return BelongsToMany<Tenant>
     */
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class)
            ->as('subscriber_tenant_pivot')
            ->withPivot('id')
            ->withTimestamps();
    }

    /**
     * @return MorphOne<Media>
     */
    public function avatar(): MorphOne
    {
        return $this->morphOne(
            Media::class,
            'model',
        );
    }

    /**
     * @return MorphMany<AccessToken>
     */
    public function accessTokens(): MorphMany
    {
        return $this->morphMany(AccessToken::class, 'tokenable');
    }

    /**
     * @return HasMany<SubscriberEvent>
     */
    public function events(): HasMany
    {
        return $this->hasMany(SubscriberEvent::class)
            ->latest('occurred_at');
    }

    /**
     * Whether the subscriber is verified or not.
     */
    public function getVerifiedAttribute(): bool
    {
        return $this->verified_at !== null;
    }

    /**
     * Subscriber full name.
     */
    public function getNameAttribute(): ?string
    {
        return $this->full_name;
    }

    /**
     * Get the data array for the model webhook.
     *
     * @return array<mixed>
     */
    public function toWebhookArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'verified_at' => $this->verified_at,
        ];
    }
}
