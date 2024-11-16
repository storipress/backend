<?php

namespace App\Enums\Progress;

use BenSampo\Enum\Attributes\Description;
use BenSampo\Enum\Enum;

/**
 * @method static static pending()
 * @method static static running()
 * @method static static done()
 * @method static static failed()
 * @method static static abort()
 *
 * @extends Enum<int>
 */
class ProgressState extends Enum
{
    #[Description('the progress was still pending')]
    public const pending = 1;

    #[Description('the progress was running')]
    public const running = 2;

    #[Description('the progress was finished')]
    public const done = 3;

    #[Description('there is something wrong when progressing')]
    public const failed = 4;

    #[Description('the progress was aborted by cron')]
    public const abort = 5;
}
