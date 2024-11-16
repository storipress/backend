<?php

namespace App\Enums\Monitor;

use App\Monitor\Actions\LogAction;
use App\Monitor\Actions\SlackAction;
use App\Monitor\BaseAction;
use BenSampo\Enum\Enum;

/**
 * @method static static log()
 * @method static static slack()
 *
 * @extends Enum<BaseAction>
 */
class Action extends Enum
{
    public const log = LogAction::class;

    public const slack = SlackAction::class;
}
