<?php

namespace App\Enums\Email;

use BenSampo\Enum\Enum;

/**
 * @method static static user()
 * @method static static subscriber()
 *
 * @extends Enum<int>
 */
class EmailUserType extends Enum
{
    public const user = 0;

    public const subscriber = 1;
}
