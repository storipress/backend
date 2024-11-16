<?php

namespace App\Enums\Link;

use BenSampo\Enum\Attributes\Description;
use BenSampo\Enum\Enum;

/**
 * @method static static builder()
 * @method static static editor()
 *
 * @extends Enum<string>
 */
class Source extends Enum
{
    #[Description('builder link')]
    public const builder = 'builder';

    #[Description('editor(article) link')]
    public const editor = 'editor';
}
