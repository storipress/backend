<?php

namespace App\Enums\Analyze;

use BenSampo\Enum\Enum;

/**
 * @method static static articlePainPoints()
 * @method static static articleParagraphPainPoints()
 * @method static static subscriberPainPoints()
 *
 * @extends Enum<int>
 */
class Type extends Enum
{
    public const articlePainPoints = 'article-pain-points';

    public const articleParagraphPainPoints = 'article-paragraph-pain-points';

    public const subscriberPainPoints = 'subscriber-pain-points';
}
