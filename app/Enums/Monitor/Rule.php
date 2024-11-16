<?php

namespace App\Enums\Monitor;

use App\Monitor\BaseRule;
use App\Monitor\Rules\ArticleContentUpdated;
use App\Monitor\Rules\ArticleDeleted;
use App\Monitor\Rules\ArticlePublished;
use App\Monitor\Rules\ConfirmMailExpired;
use App\Monitor\Rules\MassInvitation;
use App\Monitor\Rules\MaxBuildAttemptsExceeded;
use App\Monitor\Rules\PublicationUnused;
use App\Monitor\Rules\ReleaseBuild;
use App\Monitor\Rules\ResetPasswordMailExpired;
use BenSampo\Enum\Enum;

/**
 * @method static static confirmEmailExpired()
 * @method static static resetPasswordEmailExpired()
 * @method static static publicationUnused()
 * @method static static massInvitation()
 * @method static static releaseBuild()
 * @method static static articleDeleted()
 * @method static static articlePublished()
 * @method static static articleContentUpdated()
 * @method static static maxBuildAttemptsExceeded()
 *
 * @extends Enum<BaseRule>
 */
class Rule extends Enum
{
    public const confirmEmailExpired = ConfirmMailExpired::class;

    public const resetPasswordEmailExpired = ResetPasswordMailExpired::class;

    public const publicationUnused = PublicationUnused::class;

    public const massInvitation = MassInvitation::class;

    public const releaseBuild = ReleaseBuild::class;

    public const articleDeleted = ArticleDeleted::class;

    public const articlePublished = ArticlePublished::class;

    public const articleContentUpdated = ArticleContentUpdated::class;

    public const maxBuildAttemptsExceeded = MaxBuildAttemptsExceeded::class;
}
