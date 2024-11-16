<?php

namespace App\Models\Tenants;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * App\Models\Tenants\Layout
 *
 * @property int $id
 * @property string $name
 * @property string $template
 * @property array|null $data
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Tenants\Article> $articles
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Tenants\Desk> $desks
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Tenants\Page> $pages
 * @property-read \App\Models\Tenants\Image|null $preview
 *
 * @method static \Database\Factories\Tenants\LayoutFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|Layout newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Layout newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Layout onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|Layout query()
 * @method static \Illuminate\Database\Eloquent\Builder|Layout withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|Layout withoutTrashed()
 *
 * @mixin \Eloquent
 */
class Layout extends Entity
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
    ];

    /**
     * @return HasMany<Desk>
     */
    public function desks(): HasMany
    {
        return $this->hasMany(Desk::class);
    }

    /**
     * @return HasMany<Article>
     */
    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    /**
     * @return HasMany<Page>
     */
    public function pages(): HasMany
    {
        return $this->hasMany(Page::class);
    }

    /**
     * @return MorphOne<Image>
     */
    public function preview(): MorphOne
    {
        return $this->morphOne(
            Image::class,
            'imageable',
        );
    }
}
