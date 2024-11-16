<?php

namespace App\Enums\User;

use BenSampo\Enum\Enum;

/**
 * @method static static active()
 * @method static static suspended()
 * @method static static invited()
 *
 * @extends Enum<int>
 */
class Status extends Enum
{
    public const active = 0;

    public const suspended = 1;

    public const invited = 2;
}
