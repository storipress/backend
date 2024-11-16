<?php

namespace App\Models;

use App\Enums\CustomDomain\Group;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\CustomDomain
 *
 * @property int $id
 * @property string $tenant_id
 * @property string $domain
 * @property \BenSampo\Enum\Enum $group
 * @property string $hostname
 * @property string $type
 * @property string $value
 * @property bool $ok
 * @property string|null $error
 * @property string|null $last_checked_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read \App\Models\Tenant|null $tenant
 *
 * @method static \Database\Factories\CustomDomainFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|CustomDomain newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|CustomDomain newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|CustomDomain query()
 *
 * @mixin \Eloquent
 */
class CustomDomain extends Entity
{
    use HasFactory;

    /**
     * The attributes that should be cast.
     *
     * @var array<string, class-string|string>
     */
    protected $casts = [
        'group' => Group::class,
        'ok' => 'bool',
    ];

    /**
     * @return BelongsTo<Tenant, CustomDomain>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
