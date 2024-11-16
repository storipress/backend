<?php

namespace App\Models\Tenants;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Rutorika\Sortable\SortableTrait;

/**
 * App\Models\Tenants\Page
 *
 * @property int $id
 * @property int|null $layout_id
 * @property string $title
 * @property array|null $draft
 * @property array|null $current
 * @property array|null $seo
 * @property int $order
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\Tenants\Layout|null $layout
 *
 * @method static \Database\Factories\Tenants\PageFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|Page newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Page newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Page onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|Page query()
 * @method static \Illuminate\Database\Eloquent\Builder|Page sorted()
 * @method static \Illuminate\Database\Eloquent\Builder|Page withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|Page withoutTrashed()
 *
 * @mixin \Eloquent
 */
class Page extends Entity
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
        'draft' => 'array',
        'current' => 'array',
        'seo' => 'array',
    ];

    /**
     * @return BelongsTo<Layout, Page>
     */
    public function layout(): BelongsTo
    {
        return $this->belongsTo(Layout::class);
    }
}
