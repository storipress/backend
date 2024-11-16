<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * App\Models\CloudflarePage
 *
 * @property int $id
 * @property string $name
 * @property int $occupiers
 * @property array|null $raw
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CloudflarePageDeployment> $deployments
 * @property-read bool $is_almost_full
 * @property-read int $remains
 * @property-read \Stancl\Tenancy\Database\TenantCollection<int, \App\Models\Tenant> $tenants
 *
 * @method static \Database\Factories\CloudflarePageFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|CloudflarePage newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|CloudflarePage newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|CloudflarePage query()
 *
 * @mixin \Eloquent
 */
class CloudflarePage extends Entity
{
    use HasFactory;

    /**
     * the max number of tenant
     *
     * @var int
     */
    public const MAX = 3000;

    /**
     * the remains that needs to expand.
     */
    public const EXPAND = 250;

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'raw' => 'json',
    ];

    /**
     * @return HasMany<Tenant>
     */
    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }

    /**
     * @return HasMany<CloudflarePageDeployment>
     */
    public function deployments(): HasMany
    {
        return $this->hasMany(CloudflarePageDeployment::class);
    }

    public function getIsAlmostFullAttribute(): bool
    {
        return $this->remains <= self::EXPAND;
    }

    public function getRemainsAttribute(): int
    {
        return max(self::MAX - $this->occupiers, 0);
    }
}
