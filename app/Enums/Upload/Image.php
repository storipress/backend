<?php

namespace App\Enums\Upload;

use BenSampo\Enum\Enum;

/**
 * @method static static userAvatar()
 * @method static static subscriberAvatar()
 * @method static static articleHeroPhoto()
 * @method static static articleSEOImage()
 * @method static static articleContentImage()
 * @method static static blockPreviewImage()
 * @method static static layoutPreviewImage()
 * @method static static publicationLogo()
 * @method static static publicationBanner()
 * @method static static publicationFavicon()
 * @method static static otherPageContentImage()
 *
 * @extends Enum<int>
 */
class Image extends Enum
{
    public const userAvatar = 1;

    public const subscriberAvatar = 11;

    public const articleHeroPhoto = 21;

    public const articleSEOImage = 31;

    public const articleContentImage = 41;

    public const blockPreviewImage = 51;

    public const layoutPreviewImage = 61;

    public const publicationLogo = 71;

    public const publicationBanner = 81;

    public const publicationFavicon = 91;

    public const otherPageContentImage = 101;
}
