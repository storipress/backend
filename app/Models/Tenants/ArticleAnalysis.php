<?php

namespace App\Models\Tenants;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\Tenants\ArticleAnalysis
 *
 * @property int $id
 * @property int|null $article_id
 * @property array<string, int|float> $data
 * @property int|null $year
 * @property int|null $month
 * @property \Illuminate\Support\Carbon|null $date
 *
 * @method static \Illuminate\Database\Eloquent\Builder|ArticleAnalysis newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ArticleAnalysis newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ArticleAnalysis query()
 *
 * @mixin \Eloquent
 */
class ArticleAnalysis extends Entity
{
    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'data' => 'array',
        'date' => 'datetime:Y-m-d',
        'updated_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Article, ArticleAnalysis>
     */
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
