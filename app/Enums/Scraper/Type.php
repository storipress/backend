<?php

namespace App\Enums\Scraper;

use BenSampo\Enum\Attributes\Description;
use BenSampo\Enum\Enum;

/**
 * @method static static preview()
 * @method static static full()
 *
 * @extends Enum<string>
 */
class Type extends Enum
{
    #[Description('only scrape few articles for preview')]
    public const preview = 'preview';

    #[Description('scrape all articles')]
    public const full = 'full';
}
