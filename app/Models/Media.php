<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * App\Models\Media
 *
 * @property-read string $url
 * @property int $id
 * @property string $token
 * @property string|null $tenant_id
 * @property string $model_type
 * @property string $model_id
 * @property string $collection
 * @property string $path
 * @property string $mime
 * @property int $size
 * @property int $width
 * @property int $height
 * @property string|null $blurhash
 * @property mixed|null $data
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 *
 * @method static \Database\Factories\MediaFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|Media newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Media newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Media onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|Media query()
 * @method static \Illuminate\Database\Eloquent\Builder|Media withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|Media withoutTrashed()
 *
 * @mixin \Eloquent
 */
class Media extends Entity
{
    use HasFactory;
    use SoftDeletes;

    /**
     * Get media url.
     */
    public function getUrlAttribute(): string
    {
        return 'https://assets.stori.press/' . Str::after($this->path, 'assets/');
    }
}
