<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\EmailEvent
 *
 * @property int $id
 * @property string $message_id
 * @property string $record_type
 * @property string $recipient
 * @property string|null $from
 * @property string|null $description
 * @property string|null $details
 * @property string|null $tag
 * @property array|null $metadata
 * @property int|null $bounce_id
 * @property int|null $bounce_code
 * @property string|null $bounce_content
 * @property string|null $ip
 * @property string|null $user_agent
 * @property bool $first_open
 * @property string|null $link
 * @property string|null $click_location
 * @property \Illuminate\Support\Carbon $occurred_at
 * @property string $raw
 * @property-read \App\Models\Email|null $email
 *
 * @method static \Database\Factories\EmailEventFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|EmailEvent newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|EmailEvent newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|EmailEvent query()
 *
 * @property-read string|null $event_name
 *
 * @mixin \Eloquent
 */
class EmailEvent extends Entity
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
        'metadata' => 'array',
        'first_open' => 'boolean',
        'occurred_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Email, EmailEvent>
     */
    public function email(): BelongsTo
    {
        return $this->belongsTo(
            Email::class,
            'message_id',
            'message_id',
        );
    }

    /**
     * Convert record type to event name.
     */
    public function getEventNameAttribute(): ?string
    {
        return match ($this->record_type) {
            'Delivery' => 'email.received',
            'Bounce' => 'email.bounced',
            'Open' => 'email.opened',
            'Click' => 'email.link_clicked',
            default => null,
        };
    }

    /**
     * Get event data by event type.
     *
     * @return mixed[]
     */
    public function toData(): array
    {
        $method = sprintf('to%sData', $this->record_type);

        return $this->{$method}();
    }

    /**
     * Get delivery type event data.
     *
     * @return mixed[]
     */
    protected function toDeliveryData(): array
    {
        return [];
    }

    /**
     * Get bounce type event data.
     *
     * @return mixed[]
     */
    protected function toBounceData(): array
    {
        return [
            'code' => $this->bounce_code,
            'description' => $this->description,
        ];
    }

    /**
     * Get open type event data.
     *
     * @return mixed[]
     */
    protected function toOpenData(): array
    {
        return [
            'first_open' => $this->first_open,
        ];
    }

    /**
     * Get click type event data.
     *
     * @return mixed[]
     */
    protected function toClickData(): array
    {
        return [
            'link' => $this->link,
        ];
    }
}
