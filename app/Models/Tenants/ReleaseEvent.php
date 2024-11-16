<?php

namespace App\Models\Tenants;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * App\Models\Tenants\ReleaseEvent
 *
 * @property int $id
 * @property string $name
 * @property array|null $data
 * @property int|null $release_id
 * @property int $attempts
 * @property string|null $checksum
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read \App\Models\Tenants\Release|null $release
 *
 * @method static \Database\Factories\Tenants\ReleaseEventFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|ReleaseEvent newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ReleaseEvent newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ReleaseEvent query()
 *
 * @mixin \Eloquent
 */
class ReleaseEvent extends Entity
{
    use HasFactory;

    /**
     * The attributes that should be cast.
     *
     * @var array<string, class-string|string>
     */
    protected $casts = [
        'data' => 'array',
    ];

    /**
     * @return BelongsTo<Release, ReleaseEvent>
     */
    public function release(): BelongsTo
    {
        return $this->belongsTo(Release::class);
    }

    public static function isEager(string $name): bool
    {
        $list = [
            'article:publish',
            'article:schedule',
            'article:build',
            'site:build',
            'site:rebuild',
            'site:initialize',
            'domain:enable',
            'domain:disable',
            'workspace:update',
            'shopify:enable',
            'shopify:disable',
        ];

        return Str::contains($name, $list, true);
    }
}
