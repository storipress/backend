<?php

namespace App\Enums\Email;

use BenSampo\Enum\Enum;

/**
 * @method static static deliveryAndOpenIsTooClose()
 * @method static static clickBeforeOpen()
 *
 * @extends Enum<int>
 */
class EmailAbnormalType extends Enum
{
    public const deliveryAndOpenIsTooClose = 1;

    public const clickBeforeOpen = 2;
}
