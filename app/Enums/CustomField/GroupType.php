<?php

namespace App\Enums\CustomField;

use BenSampo\Enum\Enum;

/**
 * @method static static articleMetafield()
 * @method static static articleContentBlock()
 * @method static static publicationMetafield()
 * @method static static deskMetafield()
 * @method static static tagMetafield()
 *
 * @extends Enum<string>
 */
class GroupType extends Enum
{
    public const articleMetafield = 'article-metafield';

    public const articleContentBlock = 'article-content-block';

    public const publicationMetafield = 'publication-metafield';

    public const deskMetafield = 'desk-metafield';

    public const tagMetafield = 'tag-metafield';
}
