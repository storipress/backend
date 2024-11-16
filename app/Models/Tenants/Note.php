<?php

namespace App\Models\Tenants;

use Database\Factories\Tenants\ArticleThreadNoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * App\Models\Tenants\Note
 *
 * @property int $id
 * @property int $article_id
 * @property int $thread_id
 * @property int $user_id
 * @property string $content
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\Tenants\Article $article
 * @property-read \App\Models\Tenants\ArticleThread $thread
 * @property-read \App\Models\Tenants\User $user
 *
 * @method static \Database\Factories\Tenants\ArticleThreadNoteFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|Note newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Note newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Note onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|Note query()
 * @method static \Illuminate\Database\Eloquent\Builder|Note withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|Note withoutTrashed()
 *
 * @mixin \Eloquent
 */
class Note extends Entity
{
    use HasFactory;
    use SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'article_thread_notes';

    /**
     * @return BelongsTo<Article, Note>
     */
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    /**
     * @return BelongsTo<ArticleThread, Note>
     */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(ArticleThread::class);
    }

    /**
     * @return BelongsTo<User, Note>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): ArticleThreadNoteFactory
    {
        return ArticleThreadNoteFactory::new();
    }
}
