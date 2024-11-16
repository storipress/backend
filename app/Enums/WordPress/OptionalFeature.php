<?php

namespace App\Enums\WordPress;

use BenSampo\Enum\Enum;

/**
 * @method static static site()
 * @method static static yoastSeo()
 * @method static static acf()
 * @method static static acfPro()
 * @method static static rankMath()
 *
 * @extends Enum<string>
 */
class OptionalFeature extends Enum
{
    public const site = 'site';

    public const yoastSeo = 'yoast_seo';

    public const acf = 'acf';

    public const acfPro = 'acf_pro';

    public const rankMath = 'rank_math';
}
