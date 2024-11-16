<?php

namespace App\Models;

use App\Enums\Email\EmailUserType;
use App\Models\Tenants\SubscriberEvent;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * App\Models\Email
 *
 * @property int $id
 * @property string $tenant_id
 * @property int $user_id
 * @property \BenSampo\Enum\Enum $user_type
 * @property int|null $target_id
 * @property string|null $target_type
 * @property string $message_id
 * @property int $template_id
 * @property string $from
 * @property string $to
 * @property array|null $data
 * @property string $subject
 * @property string|null $content
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\EmailEvent> $events
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\EmailLink> $links
 * @property-read \App\Models\Subscriber $subscriber
 * @property-read \Illuminate\Database\Eloquent\Collection<int, SubscriberEvent> $subscriberEvents
 * @property-read Model|\Eloquent $target
 * @property-read \App\Models\Tenant|null $tenant
 * @property-read \App\Models\User $user
 *
 * @method static \Database\Factories\EmailFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|Email newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Email newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Email query()
 *
 * @mixin \Eloquent
 */
class Email extends Entity
{
    use HasFactory;

    /**
     * The attributes that should be cast.
     *
     * @var array<string, class-string|string>
     */
    protected $casts = [
        'user_type' => EmailUserType::class,
        'data' => 'array',
    ];

    /**
     * @return BelongsTo<Tenant, Email>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<User, Email>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'user_id',
        );
    }

    /**
     * @return BelongsTo<Subscriber, Email>
     */
    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(
            Subscriber::class,
            'user_id',
        );
    }

    /**
     * @return MorphTo<Model, Email>
     */
    public function target(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return HasMany<EmailEvent>
     */
    public function events(): HasMany
    {
        return $this->hasMany(
            EmailEvent::class,
            'message_id',
            'message_id',
        );
    }

    /**
     * @return HasMany<EmailLink>
     */
    public function links(): HasMany
    {
        return $this->hasMany(
            EmailLink::class,
            'message_id',
            'message_id',
        );
    }

    /**
     * @return MorphMany<SubscriberEvent>
     */
    public function subscriberEvents(): MorphMany
    {
        return $this
            ->setConnection('tenant')
            ->morphMany(
                SubscriberEvent::class,
                'target',
            );
    }

    /**
     * @return HasMany<AbnormalEmail>
     */
    public function abnormal(): HasMany
    {
        return $this->hasMany(
            AbnormalEmail::class,
            'message_id',
            'message_id',
        );
    }
}
