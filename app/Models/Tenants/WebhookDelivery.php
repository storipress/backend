<?php

namespace App\Models\Tenants;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\Tenants\WebhookDelivery
 *
 * @property int $id
 * @property string $webhook_id
 * @property string $event_uuid
 * @property int $successful
 * @property array|null $request
 * @property array|null $response
 * @property array|null $error
 * @property \Illuminate\Support\Carbon $occurred_at
 * @property-read \App\Models\Tenants\Webhook|null $webhook
 *
 * @method static \Database\Factories\Tenants\WebhookDeliveryFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|WebhookDelivery newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|WebhookDelivery newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|WebhookDelivery query()
 *
 * @mixin \Eloquent
 */
class WebhookDelivery extends Entity
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
        'request' => 'array',
        'response' => 'array',
        'error' => 'array',
        'occurred_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Webhook, WebhookDelivery>
     */
    public function webhook(): BelongsTo
    {
        return $this->belongsTo(Webhook::class)->withTrashed();
    }
}
