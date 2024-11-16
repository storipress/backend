<?php

declare(strict_types=1);

namespace App\Models\Tenants\Integrations;

use App\Models\Tenants\Integrations\Configurations\WordPressConfiguration;

/**
 * App\Models\Tenants\Integrations\WordPress
 *
 * @extends Integration<WordPressConfiguration>
 *
 * @property-read WordPressConfiguration $config
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
 * @method static \Illuminate\Database\Eloquent\Builder|WordPress newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|WordPress newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|WordPress query()
 *
 * @mixin \Eloquent
 */
class WordPress extends Integration
{
    public function getIsActivatedAttribute(): bool
    {
        return $this->activated_at !== null &&
            $this->activated_at->isPast() &&
            !$this->config->expired &&
            $this->is_connected;
    }

    public function getIsConnectedAttribute(): bool
    {
        return !empty($this->internals) &&
            $this->config->access_token &&
            $this->config->username &&
            $this->config->url;
    }
}
