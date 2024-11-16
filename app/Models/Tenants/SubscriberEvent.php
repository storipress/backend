<?php

namespace App\Models\Tenants;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * App\Models\Tenants\SubscriberEvent
 *
 * @property int $id
 * @property int $subscriber_id
 * @property int|null $target_id
 * @property string|null $target_type
 * @property string $name
 * @property array|null $data
 * @property \Illuminate\Support\Carbon $occurred_at
 * @property-read \App\Models\Tenants\Subscriber $subscriber
 * @property-read \Illuminate\Database\Eloquent\Model|\Eloquent $target
 *
 * @method static \Database\Factories\Tenants\SubscriberEventFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|SubscriberEvent newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|SubscriberEvent newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|SubscriberEvent query()
 *
 * @mixin \Eloquent
 */
class SubscriberEvent extends Entity
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
     * @return BelongsTo<Subscriber, SubscriberEvent>
     */
    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(Subscriber::class);
    }

    /**
     * @return MorphTo<\Illuminate\Database\Eloquent\Model, SubscriberEvent>
     */
    public function target(): MorphTo
    {
        return $this->morphTo();
    }
}
