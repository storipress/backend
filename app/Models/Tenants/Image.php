<?php

namespace App\Models\Tenants;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * App\Models\Tenants\Image
 *
 * @property-read string $url
 * @property int $id
 * @property string $token
 * @property string $imageable_type
 * @property int $imageable_id
 * @property string $path
 * @property string $name
 * @property string $mime
 * @property int $size
 * @property int $width
 * @property int $height
 * @property string|null $title
 * @property string|null $caption
 * @property string|null $description
 * @property array|null $transformation
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Model|\Eloquent $imageable
 *
 * @method static \Database\Factories\Tenants\ImageFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|Image newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Image newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Image onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|Image query()
 * @method static \Illuminate\Database\Eloquent\Builder|Image withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|Image withoutTrashed()
 *
 * @mixin \Eloquent
 */
class Image extends Entity
{
    use HasFactory;
    use SoftDeletes;

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'size' => 'int',
        'width' => 'int',
        'height' => 'int',
        'transformation' => 'array',
    ];

    /**
     * @return MorphTo<\Illuminate\Database\Eloquent\Model, Image>
     */
    public function imageable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get image url.
     */
    public function getUrlAttribute(): string
    {
        return 'https://assets.stori.press/' . $this->path;
    }
}
