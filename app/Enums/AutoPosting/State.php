<?php

namespace App\Enums\AutoPosting;

use BenSampo\Enum\Attributes\Description;
use BenSampo\Enum\Enum;

/**
 * @method static static none()
 * @method static static initialized()
 * @method static static waiting()
 * @method static static posted()
 * @method static static cancelled()
 * @method static static aborted()
 *
 * @extends Enum<int>
 */
final class State extends Enum
{
    #[Description('the auto posting was past')]
    public const none = 0;

    #[Description('the auto posting was initialized')]
    public const initialized = 1;

    #[Description('the auto posting was waiting for post')]
    public const waiting = 2;

    #[Description('the auto posting was posted')]
    public const posted = 3;

    #[Description('the auto posting was cancelled')]
    public const cancelled = 4;

    #[Description('the auto posting was aborted')]
    public const aborted = 5;
}
