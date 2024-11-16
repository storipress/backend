<?php

namespace App\Enums\Site;

use BenSampo\Enum\Enum;

/**
 * @method static static v1()
 * @method static static v2()
 * @method static static karbon()
 * @method static static custom()
 *
 * @extends Enum<string>
 */
class Generator extends Enum
{
    public const v1 = 'v1';

    public const v2 = 'v2';

    public const karbon = 'karbon';

    public const custom = 'custom';
}
