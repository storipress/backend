<?php

declare(strict_types=1);

namespace App\Enums\CustomDomain;

use BenSampo\Enum\Enum;

/**
 * @method static static site()
 * @method static static mail()
 * @method static static redirect()
 *
 * @extends Enum<string>
 */
final class Group extends Enum
{
    public const site = 'site';

    public const mail = 'mail';

    public const redirect = 'redirect';
}
