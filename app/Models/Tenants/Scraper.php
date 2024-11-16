<?php

namespace App\Models\Tenants;

use App\Enums\Scraper\State;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * App\Models\Tenants\Scraper
 *
 * @property int $id
 * @property \BenSampo\Enum\Enum $state
 * @property array|null $data
 * @property int $total
 * @property int $successful
 * @property int $failed
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $finished_at
 * @property \Illuminate\Support\Carbon|null $cancelled_at
 * @property \Illuminate\Support\Carbon|null $failed_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Tenants\ScraperArticle> $articles
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Tenants\ScraperSelector> $selectors
 *
 * @method static \Database\Factories\Tenants\ScraperFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|Scraper newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Scraper newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Scraper query()
 *
 * @mixin \Eloquent
 */
class Scraper extends Entity
{
    use HasFactory;

    /**
     * The attributes that should be cast.
     *
     * @var array<string, class-string|string>
     */
    protected $casts = [
        'state' => State::class,
        'data' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    /**
     * @return HasMany<ScraperSelector>
     */
    public function selectors(): HasMany
    {
        return $this->hasMany(ScraperSelector::class);
    }

    /**
     * @return HasMany<ScraperArticle>
     */
    public function articles(): HasMany
    {
        return $this->hasMany(ScraperArticle::class);
    }
}
