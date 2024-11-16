<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\SubscriberEvent
 *
 * @property int $id
 * @property int $subscriber_id
 * @property string $name
 * @property array|null $data
 * @property string $occurred_on
 * @property-read \App\Models\Subscriber|null $subscriber
 *
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
}
