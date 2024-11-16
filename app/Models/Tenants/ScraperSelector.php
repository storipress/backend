<?php

namespace App\Models\Tenants;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * App\Models\Tenants\ScraperSelector
 *
 * @property int $id
 * @property int $scraper_id
 * @property string $type
 * @property string|null $value
 * @property array|null $data
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 *
 * @method static \Database\Factories\Tenants\ScraperSelectorFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|ScraperSelector newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ScraperSelector newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ScraperSelector onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|ScraperSelector query()
 * @method static \Illuminate\Database\Eloquent\Builder|ScraperSelector withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|ScraperSelector withoutTrashed()
 *
 * @mixin \Eloquent
 */
class ScraperSelector extends Entity
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
}
