<?php

namespace App\Models;

use App\Enums\Link\Source;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * App\Models\Link
 *
 * @property int $id
 * @property string $tenant_id
 * @property \BenSampo\Enum\Enum $source
 * @property bool $reference
 * @property string|null $target_tenant
 * @property string|null $target_type
 * @property string|null $target_id
 * @property string|null $value
 * @property \Illuminate\Support\Carbon|null $last_checked_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read Model|\Eloquent $target
 *
 * @method static \Database\Factories\LinkFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|Link newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Link newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Link query()
 *
 * @mixin \Eloquent
 */
class Link extends Entity
{
    use HasFactory;

    /**
     * The attributes that should be cast.
     *
     * @var array<string, class-string|string>
     */
    protected $casts = [
        'source' => Source::class,
        'reference' => 'bool',
        'last_checked_at' => 'datetime',
    ];

    /**
     * @return MorphTo<Model, Link>
     */
    public function target(): MorphTo
    {
        return $this
            ->setConnection('tenant')
            ->morphTo();
    }
}
