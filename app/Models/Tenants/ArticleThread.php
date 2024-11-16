<?php

namespace App\Models\Tenants;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * App\Models\Tenants\ArticleThread
 *
 * @property int $id
 * @property int $article_id
 * @property array $position
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $resolved_at
 * @property-read \App\Models\Tenants\Article $article
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Tenants\Note> $notes
 *
 * @method static \Database\Factories\Tenants\ArticleThreadFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|ArticleThread newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ArticleThread newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ArticleThread onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|ArticleThread query()
 * @method static \Illuminate\Database\Eloquent\Builder|ArticleThread withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|ArticleThread withoutTrashed()
 *
 * @mixin \Eloquent
 */
class ArticleThread extends Entity
{
    use HasFactory;
    use SoftDeletes;

    /**
     * The name of the "deleted at" column.
     *
     * @var string
     */
    public const DELETED_AT = 'resolved_at';

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'position' => 'array',
    ];

    /**
     * @return BelongsTo<Article, ArticleThread>
     */
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    /**
     * @return HasMany<Note>
     */
    public function notes(): HasMany
    {
        return $this->hasMany(
            Note::class,
            'thread_id',
        );
    }
}
