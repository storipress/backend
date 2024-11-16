<?php

declare(strict_types=1);

namespace App\Models\Tenants\Integrations;

use App\Models\Tenants\Integrations\Configurations\WebflowConfiguration;

/**
 * App\Models\Tenants\Integrations\Webflow
 *
 * @extends Integration<WebflowConfiguration>
 *
 * @property-read WebflowConfiguration $config
 * @property string $key
 * @property array $data
 * @property array|null $internals
 * @property \Illuminate\Support\Carbon|null $activated_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read array<mixed>|null $configuration
 * @property-read bool $is_activated
 * @property-read bool $is_connected
 *
 * @method static Builder|Integration activated()
 * @method static \Illuminate\Database\Eloquent\Builder|Webflow newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Webflow newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Webflow query()
 *
 * @mixin \Eloquent
 */
class Webflow extends Integration
{
    public function getIsActivatedAttribute(): bool
    {
        return $this->activated_at !== null &&
            $this->activated_at->isPast() &&
            $this->is_connected &&
            $this->config->site_id !== null;
    }

    public function getIsConnectedAttribute(): bool
    {
        return $this->config->v2 &&
            !$this->config->expired;
    }
}
