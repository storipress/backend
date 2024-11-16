<?php

namespace App\Models\Tenants;

use App\Enums\AutoPosting\State;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\Tenants\ArticleAutoPosting
 *
 * @property int $id
 * @property int $article_id
 * @property string $platform
 * @property string|null $target_id
 * @property string|null $domain
 * @property string|null $prefix
 * @property string|null $pathname
 * @property \BenSampo\Enum\Enum $state
 * @property array|null $data
 * @property \Illuminate\Support\Carbon|null $scheduled_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read \App\Models\Tenants\Article|null $article
 * @property-read \App\Models\Tenants\Integration|null $integration
 *
 * @method static \Database\Factories\Tenants\ArticleAutoPostingFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|ArticleAutoPosting newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ArticleAutoPosting newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ArticleAutoPosting query()
 *
 * @mixin \Eloquent
 */
class ArticleAutoPosting extends Entity
{
    use HasFactory;

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'data' => 'array',
        'state' => State::class,
        'scheduled_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Article, ArticleAutoPosting>
     */
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    /**
     * @return BelongsTo<Integration, ArticleAutoPosting>
     */
    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }
}
