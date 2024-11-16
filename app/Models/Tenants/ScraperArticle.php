<?php

namespace App\Models\Tenants;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * App\Models\Tenants\ScraperArticle
 *
 * @property int $id
 * @property int $scraper_id
 * @property string $path
 * @property array|null $data
 * @property int|null $article_id
 * @property bool $successful
 * @property \Illuminate\Support\Carbon|null $scraped_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read bool $scraped
 *
 * @method static \Database\Factories\Tenants\ScraperArticleFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|ScraperArticle newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ScraperArticle newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ScraperArticle onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|ScraperArticle query()
 * @method static \Illuminate\Database\Eloquent\Builder|ScraperArticle withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|ScraperArticle withoutTrashed()
 *
 * @mixin \Eloquent
 */
class ScraperArticle extends Entity
{
    use HasFactory;
    use SoftDeletes;

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'data' => 'array',
        'successful' => 'bool',
        'scraped_at' => 'datetime',
    ];

    public function getScrapedAttribute(): bool
    {
        return $this->scraped_at !== null;
    }
}
