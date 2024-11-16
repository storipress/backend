<?php

declare(strict_types=1);

namespace App\Enums\Assistant;

use BenSampo\Enum\Enum;

/**
 * @method static static gpt4()
 * @method static static gpt4Extend()
 * @method static static gpt4Preview()
 * @method static static gpt4Turbo()
 * @method static static gpt4TurboPreview()
 * @method static static gpt4O()
 * @method static static gpt3()
 * @method static static gpt3Extend()
 * @method static static mixtral8x7b32768()
 *
 * @extends Enum<string>
 */
final class Model extends Enum
{
    public const gpt4 = 'gpt-4';

    public const gpt4Extend = 'gpt-4-32k';

    public const gpt4Preview = 'gpt-4-1106-preview';

    public const gpt4Turbo = 'gpt-4-turbo';

    public const gpt4TurboPreview = 'gpt-4-turbo-preview';

    public const gpt4O = 'gpt-4o';

    public const gpt3 = 'gpt-3.5-turbo';

    public const gpt3Extend = 'gpt-3.5-turbo-16k';

    public const mixtral8x7b32768 = 'mixtral-8x7b-32768';
}
