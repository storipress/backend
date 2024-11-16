<?php

namespace App\Enums\AccessToken;

use BenSampo\Enum\Attributes\Description;
use BenSampo\Enum\Enum;

/**
 * @method static static internal()
 * @method static static user()
 * @method static static subscriber()
 * @method static static tenant()
 *
 * @extends Enum<string>
 */
class Type extends Enum
{
    #[Description('internal server data exchange')]
    public const internal = 'spi';

    #[Description('user access token')]
    public const user = 'spu';

    #[Description('subscriber access token')]
    public const subscriber = 'sps';

    #[Description('publication access token')]
    public const tenant = 'spt';
}
