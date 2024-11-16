<?php

namespace App\Models\Tenants;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * App\Models\Tenants\Block
 *
 * @property int $id
 * @property string $uuid
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\Tenants\Image|null $preview
 *
 * @method static \Database\Factories\Tenants\BlockFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|Block newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Block newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Block onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|Block query()
 * @method static \Illuminate\Database\Eloquent\Builder|Block withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|Block withoutTrashed()
 *
 * @mixin \Eloquent
 */
class Block extends Entity
{
    use HasFactory;
    use SoftDeletes;

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
