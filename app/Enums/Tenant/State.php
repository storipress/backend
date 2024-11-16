<?php

namespace App\Enums\Tenant;

use BenSampo\Enum\Enum;

/**
 * @method static static uninitialized()
 * @method static static online()
 * @method static static deleted()
 * @method static static notFound()
 *
 * @extends Enum<string>
 */
class State extends Enum
{
    public const uninitialized = 'uninitialized';

    public const online = 'online';

    public const deleted = 'deleted';

    public const notFound = 'not-found';
}
