<?php

namespace App\Enums\Article;

use BenSampo\Enum\Attributes\Description;
use BenSampo\Enum\Enum;

/**
 * @method static static free()
 * @method static static member()
 * @method static static subscriber()
 *
 * @extends Enum<int>
 */
class Plan extends Enum
{
    #[Description('public accessible article')]
    public const free = 0;

    #[Description('login required article')]
    public const member = 1;

    #[Description('paid member only article')]
    public const subscriber = 2;
}
