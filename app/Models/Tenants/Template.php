<?php

namespace App\Models\Tenants;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * App\Models\Tenants\Template
 *
 * @property int $id
 * @property string $key
 * @property string $group
 * @property string $type
 * @property string $path
 * @property string|null $name
 * @property string|null $description
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read string $url
 *
 * @method static \Database\Factories\Tenants\TemplateFactory factory($count = null, $state = [])
 * @method static Builder|Template newModelQuery()
 * @method static Builder|Template newQuery()
 * @method static Builder|Template onlyTrashed()
 * @method static Builder|Template query()
 * @method static Builder|Template siteTemplate()
 * @method static Builder|Template withTrashed()
 * @method static Builder|Template withoutTrashed()
 *
 * @mixin \Eloquent
 */
class Template extends Entity
{
    use HasFactory;
    use SoftDeletes;

    public function getUrlAttribute(): string
    {
        return assets_url($this->path);
    }

    /**
     * @param  Builder<Template>  $query
     * @return Builder<Template>
     */
    public function scopeSiteTemplate(Builder $query): Builder
    {
        return $query->where('group', 'LIKE', 'site-%');
    }
}
