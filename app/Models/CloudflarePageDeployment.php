<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * App\Models\CloudflarePageDeployment
 *
 * @property string $id
 * @property int $cloudflare_page_id
 * @property string $tenant_id
 * @property array $raw
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\CloudflarePage|null $page
 * @property-read \App\Models\Tenant|null $tenant
 *
 * @method static \Database\Factories\CloudflarePageDeploymentFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|CloudflarePageDeployment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|CloudflarePageDeployment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|CloudflarePageDeployment onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|CloudflarePageDeployment query()
 * @method static \Illuminate\Database\Eloquent\Builder|CloudflarePageDeployment withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|CloudflarePageDeployment withoutTrashed()
 *
 * @mixin \Eloquent
 */
class CloudflarePageDeployment extends Entity
{
    use HasFactory;
    use SoftDeletes;

    /**
     * {@inheritdoc}
     */
    protected $keyType = 'string';

    /**
     * {@inheritdoc}
     */
    public $incrementing = false;

    /**
     * {@inheritdoc}
     */
    public $timestamps = false;

    /**
     * {@inheritdoc}
     *
     * @var array<string, string>
     */
    protected $casts = [
        'raw' => 'json',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<CloudflarePage, CloudflarePageDeployment>
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(
            CloudflarePage::class,
            'cloudflare_page_id',
        );
    }

    /**
     * @return BelongsTo<Tenant, CloudflarePageDeployment>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
