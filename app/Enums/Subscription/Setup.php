<?php

namespace App\Enums\Subscription;

use BenSampo\Enum\Enum;

/**
 * @method static static none()
 * @method static static waitConnectStripe()
 * @method static static waitImport()
 * @method static static waitNextStage()
 * @method static static done()
 *
 * @extends Enum<int>
 */
class Setup extends Enum
{
    public const none = 0;

    public const waitConnectStripe = 1;

    public const waitImport = 2;

    public const waitNextStage = 3;

    public const done = 4;
}
