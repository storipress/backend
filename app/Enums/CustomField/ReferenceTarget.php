<?php

namespace App\Enums\CustomField;

use App\Models\Tenants\Article;
use App\Models\Tenants\Desk;
use App\Models\Tenants\Tag;
use App\Models\Tenants\WebflowReference;
use App\Models\User;
use BenSampo\Enum\Enum;

/**
 * @method static static article()
 * @method static static desk()
 * @method static static tag()
 * @method static static user()
 * @method static static webflow()
 *
 * @extends Enum<class-string>
 */
class ReferenceTarget extends Enum
{
    public const article = Article::class;

    public const desk = Desk::class;

    public const tag = Tag::class;

    public const user = User::class;

    public const webflow = WebflowReference::class;
}
