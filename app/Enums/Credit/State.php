<?php

namespace App\Enums\Credit;

use BenSampo\Enum\Enum;

/**
 * @method static static draft()
 * @method static static available()
 * @method static static used()
 * @method static static void()
 *
 * @extends Enum<int>
 */
class State extends Enum
{
    public const draft = 1;

    public const available = 2;

    public const used = 3;

    public const void = 4;
}
