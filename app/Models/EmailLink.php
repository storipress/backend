<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\EmailLink
 *
 * @property int $id
 * @property string|null $message_id
 * @property string $link
 * @property int $clicks
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read \App\Models\Email|null $email
 *
 * @method static \Database\Factories\EmailLinkFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|EmailLink newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|EmailLink newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|EmailLink query()
 *
 * @mixin \Eloquent
 */
class EmailLink extends Entity
{
    use HasFactory;

    /**
     * @return BelongsTo<Email, EmailLink>
     */
    public function email(): BelongsTo
    {
        return $this->belongsTo(
            Email::class,
            'message_id',
            'message_id',
        );
    }
}
