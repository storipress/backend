<?php

namespace App\Enums\Site;

use BenSampo\Enum\Enum;

/**
 * @method static static storipress()
 * @method static static webflow()
 * @method static static shopify()
 * @method static static wordpress()
 *
 * @extends Enum<string>
 */
class Hosting extends Enum
{
    public const storipress = 'storipress';

    public const webflow = 'webflow';

    public const shopify = 'shopify';

    public const wordpress = 'wordpress';
}
