<?php

namespace App\Enums\Webflow;

use BenSampo\Enum\Enum;

/**
 * @method static static blog()
 * @method static static author()
 * @method static static tag()
 * @method static static desk()
 *
 * @extends Enum<string>
 */
class CollectionType extends Enum
{
    public const blog = 'blog';

    public const author = 'author';

    public const tag = 'tag';

    public const desk = 'desk';
}
