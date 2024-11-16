<?php

namespace App\Models\Tenants;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Rutorika\Sortable\SortableTrait;

/**
 * App\Models\Tenants\Stage
 *
 * @property int $id
 * @property string $name
 * @property string $color
 * @property string|null $icon
 * @property int $order
 * @property bool $ready
 * @property bool $default
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Tenants\Article> $articles
 *
 * @method static Builder|Stage default()
 * @method static \Database\Factories\Tenants\StageFactory factory($count = null, $state = [])
 * @method static Builder|Stage newModelQuery()
 * @method static Builder|Stage newQuery()
 * @method static Builder|Stage onlyTrashed()
 * @method static Builder|Stage query()
 * @method static Builder|Stage ready()
 * @method static Builder|Stage sorted()
 * @method static Builder|Stage withTrashed()
 * @method static Builder|Stage withoutTrashed()
 *
 * @mixin \Eloquent
 */
class Stage extends Entity
{
    use HasFactory;
    use SoftDeletes;
    use SortableTrait;

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'order' => 'int',
        'ready' => 'bool',
        'default' => 'bool',
    ];

    /**
     * @return HasMany<Article>
     */
    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    /**
     * Scope a query to only include default stage.
     *
     * @param  Builder<Stage>  $query
     * @return Builder<Stage>
     */
    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('default', '=', true);
    }

    /**
     * Scope a query to only include ready stage.
     *
     * @param  Builder<Stage>  $query
     * @return Builder<Stage>
     */
    public function scopeReady(Builder $query): Builder
    {
        return $query->where('ready', '=', true);
    }
}
