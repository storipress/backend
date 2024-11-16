<?php

namespace App\Enums\Article;

use BenSampo\Enum\Enum;

/**
 * @method static static none()
 * @method static static immediate()
 * @method static static schedule()
 *
 * @extends Enum<int>
 */
class PublishType extends Enum
{
    public const none = 0;

    public const immediate = 1;

    public const schedule = 2;
}
