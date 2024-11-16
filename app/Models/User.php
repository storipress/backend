<?php

namespace App\Models;

use App\Authentication\Authenticatable;
use App\Enums\User\Gender;
use App\Models\Attributes\Avatar;
use App\Models\Attributes\FullName;
use App\Models\Attributes\IntercomHashIdentity;
use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Illuminate\Notifications\Notifiable;
use Laravel\Cashier\Billable;
use Laravel\Cashier\PaymentMethod;
use Laravel\Cashier\Subscription;
use Stripe\Card;

/**
 * App\Models\User
 *
 * @property-read UserStatus $tenant_user_pivot
 * @property int $id
 * @property string $email
 * @property string $password
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string|null $slug
 * @property \BenSampo\Enum\Enum $gender
 * @property \Illuminate\Support\Carbon|null $birthday
 * @property string|null $phone_number
 * @property string|null $location
 * @property string|null $bio
 * @property string|null $job_title
 * @property string|null $contact_email
 * @property string|null $website
 * @property array|null $socials
 * @property string $signed_up_source
 * @property string|null $intercom_id
 * @property string|null $stripe_id
 * @property string|null $pm_type
 * @property string|null $pm_last_four
 * @property \Illuminate\Support\Carbon|null $trial_ends_at
 * @property string|null $verified_at
 * @property array|null $data
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\AccessToken> $accessTokens
 * @property-read string $avatar
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Credit> $credits
 * @property-read int|null $age
 * @property-read string|null $full_name
 * @property-read string $intercom_hash_identity
 * @property-read string $login_email
 * @property-read mixed[]|null $meta
 * @property-read string|null $name
 * @property-read bool $verified
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PasswordReset> $password_resets
 * @property-read \Stancl\Tenancy\Database\TenantCollection<int, \App\Models\Tenant> $publications
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Subscription> $subscriptions
 * @property-read \Stancl\Tenancy\Database\TenantCollection<int, \App\Models\Tenant> $tenants
 *
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|User query()
 *
 * @mixin \Eloquent
 */
class User extends Entity implements AuthenticatableContract, AuthorizableContract
{
    use Authenticatable;
    use Authorizable;
    use Avatar;
    use Billable;
    use FullName;
    use HasFactory;
    use IntercomHashIdentity;
    use Notifiable;
    use Sluggable;

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'phone_number',
        'gender',
        'birthday',
        'age',
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, class-string|string>
     */
    protected $casts = [
        'birthday' => 'date',
        'gender' => Gender::class,
        'socials' => 'array',
        'trial_ends_at' => 'datetime',
        'data' => 'array',
    ];

    /**
     * The relations to eager load on every query.
     *
     * @var array<int, string>
     */
    protected $with = [
        'avatar',
    ];

    /**
     * @return MorphOne<Media>
     */
    public function avatar(): MorphOne
    {
        return $this->morphOne(
            Media::class,
            'model',
        )->latest('id');
    }

    /**
     * @return HasMany<Credit>
     */
    public function credits(): HasMany
    {
        return $this->hasMany(Credit::class);
    }

    /**
     * User joined tenants.
     *
     * @return BelongsToMany<Tenant>
     */
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class)
            ->as('tenant_user_pivot')
            ->using(UserStatus::class)
            ->withPivot('id', 'status', 'hidden', 'role');
    }

    /**
     * Publications owned by user.
     *
     * @return HasMany<Tenant>
     */
    public function publications(): HasMany
    {
        return $this->hasMany(Tenant::class)
            ->orderBy('created_at');
    }

    /**
     * @return HasMany<PasswordReset>
     */
    public function password_resets(): HasMany
    {
        return $this->hasMany(PasswordReset::class);
    }

    /**
     * @return MorphMany<AccessToken>
     */
    public function accessTokens(): MorphMany
    {
        return $this->morphMany(AccessToken::class, 'tokenable');
    }

    /**
     * User login email.
     */
    public function getLoginEmailAttribute(): string
    {
        return $this->email;
    }

    /**
     * User full name.
     */
    public function getNameAttribute(): ?string
    {
        return $this->full_name;
    }

    /**
     * User age.
     */
    public function getAgeAttribute(): ?int
    {
        return $this->birthday?->diffInYears(now());
    }

    /**
     * User is verified or not.
     */
    public function getVerifiedAttribute(): bool
    {
        return ! is_null($this->verified_at);
    }

    /**
     * @return mixed[]|null
     */
    public function getMetaAttribute(): ?array
    {
        return $this->data ?: [];
    }

    /**
     * @return Attribute<string, string>
     */
    protected function password(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => decrypt($value), // @phpstan-ignore-line
            set: fn ($value) => encrypt($value),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function subscribed(): bool
    {
        return (bool) $this->subscriptions
            ->whereIn('stripe_status', ['active', 'trialing'])
            ->sortBy('created_at')
            ->first()
            ?->valid();
    }

    /**
     * {@inheritdoc}
     */
    public function subscription(): ?Subscription
    {
        return $this->subscriptions
            ->whereIn('stripe_status', ['active', 'trialing'])
            ->sortBy('created_at')
            ->first();
    }

    /**
     * Get the address that should be synced to Stripe.
     *
     * @return array{
     *   city: string|null,
     *   country: string|null,
     *   line1: string|null,
     *   line2: string|null,
     *   postal_code: string|null,
     *   state: string|null,
     * }|null
     */
    public function stripeAddress(): ?array
    {
        $method = $this->defaultPaymentMethod();

        if ($method instanceof PaymentMethod) {
            return $method->asStripePaymentMethod()
                ->billing_details
                ->toArray()['address'] ?? null;
        } elseif ($method instanceof Card) {
            return [
                'city' => $method->address_city,
                'country' => $method->address_country,
                'line1' => $method->address_line1,
                'line2' => $method->address_line2,
                'postal_code' => $method->address_zip,
                'state' => $method->address_state,
            ];
        } else {
            return null;
        }
    }

    /**
     * Return the sluggable configuration array for this model.
     *
     * @return array<string, array<string, bool|int|string>>
     */
    public function sluggable(): array
    {
        return [
            'slug' => [
                'source' => 'full_name',
                'onUpdate' => true,
                'includeTrashed' => true,
                'maxLength' => 250,
            ],
        ];
    }

    /**
     * The channels the user receives notification broadcasts on.
     */
    public function receivesBroadcastNotificationsOn(): string
    {
        return sprintf('n.%s', $this->intercom_hash_identity);
    }
}
