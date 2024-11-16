<?php

namespace App\Enums\Link;

use App\Models\Tenants\Article;
use App\Models\Tenants\Desk;
use App\Models\Tenants\Page;
use App\Models\Tenants\Tag;
use App\Models\Tenants\User;
use BenSampo\Enum\Enum;

/**
 * @method static static article()
 * @method static static desk()
 * @method static static tag()
 * @method static static user()
 * @method static static page()
 *
 * @extends Enum<class-string>
 */
class Target extends Enum
{
    public const article = Article::class;

    public const desk = Desk::class;

    public const tag = Tag::class;

    public const user = User::class;

    public const page = Page::class;
}
