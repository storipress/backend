<?php

namespace App\Enums\Release;

use BenSampo\Enum\Attributes\Description;
use BenSampo\Enum\Enum;

/**
 * @method static static done()
 * @method static static aborted()
 * @method static static canceled()
 * @method static static queued()
 * @method static static error()
 * @method static static preparing()
 * @method static static generating()
 * @method static static compressing()
 * @method static static uploading()
 *
 * @extends Enum<int>
 */
class State extends Enum
{
    #[Description('the release was built successfully')]
    public const done = 1;

    #[Description('the release was aborted by user')]
    public const aborted = 2;

    #[Description('the release was canceled by system, e.g. a new release is triggered')]
    public const canceled = 3;

    #[Description('the release was still in queue, this is default state')]
    public const queued = 4;

    #[Description('there is something wrong when building the site')]
    public const error = 5;

    #[Description('generator is preparing the site data')]
    public const preparing = 6;

    #[Description('generator is building static site data')]
    public const generating = 7;

    #[Description('generator is compressing the site data for uploading to our CDN servers')]
    public const compressing = 8;

    #[Description('generator is uploading the archive file to our CDN servers')]
    public const uploading = 9;
}
