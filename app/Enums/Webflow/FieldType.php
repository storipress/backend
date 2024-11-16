<?php

namespace App\Enums\Webflow;

use BenSampo\Enum\Enum;

/**
 * @method static static plainText()
 * @method static static richText()
 * @method static static image()
 * @method static static multiImage()
 * @method static static videoLink()
 * @method static static link()
 * @method static static email()
 * @method static static phone()
 * @method static static number()
 * @method static static dateTime()
 * @method static static switch()
 * @method static static color()
 * @method static static option()
 * @method static static file()
 * @method static static reference()
 * @method static static multiReference()
 * @method static static user()
 * @method static static skuSettings()
 * @method static static skuValues()
 * @method static static price()
 * @method static static membershipPlan()
 * @method static static textOption()
 * @method static static multiExternalFile()
 *
 * @extends Enum<string>
 */
class FieldType extends Enum
{
    public const plainText = 'PlainText';

    public const richText = 'RichText';

    public const image = 'Image';

    public const multiImage = 'MultiImage';

    public const videoLink = 'VideoLink';

    public const link = 'Link';

    public const email = 'Email';

    public const phone = 'Phone';

    public const number = 'Number';

    public const dateTime = 'DateTime';

    public const switch = 'Switch';

    public const color = 'Color';

    public const option = 'Option';

    public const file = 'File';

    public const reference = 'Reference';

    public const multiReference = 'MultiReference';

    public const user = 'User';

    public const skuSettings = 'SkuSettings';

    public const skuValues = 'SkuValues';

    public const price = 'Price';

    public const membershipPlan = 'MembershipPlan';

    public const textOption = 'TextOption';

    public const multiExternalFile = 'MultiExternalFile';
}
