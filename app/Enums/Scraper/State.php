<?php

namespace App\Enums\Scraper;

use BenSampo\Enum\Attributes\Description;
use BenSampo\Enum\Enum;

/**
 * @method static static initialized()
 * @method static static processing()
 * @method static static completed()
 *
 * @extends Enum<int>
 */
class State extends Enum
{
    #[Description('the scraper was initialized')]
    public const initialized = 1;

    #[Description('the scraper is processing')]
    public const processing = 2;

    #[Description('the scraper was completed, e.g. done, failed or cancelled')]
    public const completed = 3;
}
