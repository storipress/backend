<?php

namespace App\Enums\CustomField;

use BenSampo\Enum\Enum;

/**
 * @method static static text()
 * @method static static number()
 * @method static static color()
 * @method static static url()
 * @method static static boolean()
 * @method static static select()
 * @method static static richText()
 * @method static static file()
 * @method static static date()
 * @method static static json()
 * @method static static reference()
 *
 * @extends Enum<string>
 */
class Type extends Enum
{
    public const text = 'text';

    public const number = 'number';

    public const color = 'color';

    public const url = 'url';

    public const boolean = 'boolean';

    public const select = 'select';

    public const richText = 'rich-text';

    public const file = 'file';

    public const date = 'date';

    public const json = 'json';

    public const reference = 'reference';
}
