<?php

namespace App\Enums\Article;

use BenSampo\Enum\Enum;

/**
 * @method static static dateCreated()
 * @method static static dateCreatedDesc()
 * @method static static articleName()
 * @method static static articleNameDesc()
 * @method static static dateUpdated()
 * @method static static dateUpdatedDesc()
 *
 * @extends Enum<int>
 */
class SortBy extends Enum
{
    public const dateCreated = 0;

    public const dateCreatedDesc = 1;

    public const articleName = 2;

    public const articleNameDesc = 3;

    public const dateUpdated = 4;

    public const dateUpdatedDesc = 5;
}
