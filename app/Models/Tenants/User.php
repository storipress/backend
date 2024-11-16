<?php

namespace App\Models\Tenants;

use App\Enums\User\Status;
use App\Models\Attributes\IntercomHashIdentity;
use App\Models\User as BaseUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * App\Models\Tenants\User
 *
 * @property int|null $wordpress_id
 * @property-read string|null $full_name
 * @property-read string|null $slug
 * @property-read string|null $bio
 * @property-read string|null $contact_email
 * @property-read string|null $job_title
 * @property-read string $email
 * @property-read array<string, string|null>|null $socials
 * @property-read string $avatar
 * @property int $id
 * @property string|null $webflow_id
 * @property string $role
 * @property \BenSampo\Enum\Enum $status
 * @property \Illuminate\Support\Carbon|null $suspended_at
 * @property \Illuminate\Support\Carbon|null $last_seen_at
 * @property \Illuminate\Support\Carbon|null $last_action_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Tenants\UserActivity> $activities
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Tenants\Article> $articles
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Tenants\Desk> $desks
 * @property-read string $intercom_hash_identity
 * @property-read bool $suspended
 * @property-read BaseUser|null $parent
 *
 * @method static \Database\Factories\Tenants\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|User query()
 *
 * @mixin \Eloquent
 */
class User extends Entity
{
    use HasFactory;
    use IntercomHashIdentity;

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The attributes that should be cast.
     *
     * @var array<string, class-string|string>
     */
    protected $casts = [
        'status' => Status::class,
        'suspended_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'last_action_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * The relations to eager load on every query.
     *
     * @var array<int, string>
     */
    protected $with = [
        'parent',
    ];

    /**
     * Get an attribute from the model.
     *
     * @param  string  $key
     */
    public function getAttribute($key): mixed
    {
        $value = parent::getAttribute($key);

        if ($value !== null) {
            return $value;
        }

        return $this->parent?->getAttribute($key);
    }

    /**
     * @return BelongsTo<BaseUser, User>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(BaseUser::class, 'id');
    }

    /**
     * @return BelongsToMany<Desk>
     */
    public function desks(): BelongsToMany
    {
        return $this->belongsToMany(Desk::class);
    }

    /**
     * @return BelongsToMany<Article>
     */
    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(Article::class, 'article_author');
    }

    /**
     * @return HasMany<UserActivity>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(UserActivity::class);
    }

    /**
     * Whether the user is suspended or not.
     */
    public function getSuspendedAttribute(): bool
    {
        return Status::suspended()->is($this->status);
    }

    /**
     * Is user belongs to admin or not.
     */
    public function isAdmin(): bool
    {
        return in_array($this->role, ['owner', 'admin'], true);
    }

    /**
     * Check user is assigned to target desk.
     */
    public function isInDesk(Desk $desk): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return $this->desks
            ->where('id', '=', $desk->getKey())
            ->isNotEmpty();
    }

    /**
     * Check role level is higher than target.
     */
    public function isLevelHigherThan(User $target): bool
    {
        $mapping = [
            'owner' => 10,
            'admin' => 8,
            'editor' => 6,
            'author' => 4,
            'contributor' => 2,
        ];

        return ($mapping[$this->role] ?? 0) > ($mapping[$target->role] ?? 0);
    }
}
