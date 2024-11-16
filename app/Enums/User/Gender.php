<?php

namespace App\Enums\User;

use BenSampo\Enum\Enum;

/**
 * @method static static other()
 * @method static static male()
 * @method static static female()
 *
 * @extends Enum<int>
 */
class Gender extends Enum
{
    public const other = 0;

    public const male = 1;

    public const female = 2;
}
