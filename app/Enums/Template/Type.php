<?php

namespace App\Enums\Template;

use BenSampo\Enum\Enum;

/**
 * @method static static site()
 * @method static static editorBlock()
 * @method static static editorBlockSsr()
 * @method static static articleLayout()
 * @method static static builderBlock()
 *
 * @extends Enum<string>
 */
class Type extends Enum
{
    public const site = 'site';

    public const editorBlock = 'editor-block';

    public const editorBlockSsr = 'editor-block-ssr';

    public const articleLayout = 'article-layout';

    public const builderBlock = 'builder-block';
}
