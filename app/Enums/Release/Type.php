<?php

namespace App\Enums\Release;

use BenSampo\Enum\Attributes\Description;
use BenSampo\Enum\Enum;

/**
 * @method static static article()
 *
 * @extends Enum<string>
 */
class Type extends Enum
{
    #[Description('article type build')]
    public const article = 'article';
}
