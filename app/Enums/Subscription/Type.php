<?php

namespace App\Enums\Subscription;

use BenSampo\Enum\Enum;

/**
 * @method static static free()
 * @method static static subscribed()
 * @method static static unsubscribed()
 *
 * @extends Enum<int>
 */
class Type extends Enum
{
    public const free = 0;

    public const subscribed = 1;

    public const unsubscribed = 2;
}
