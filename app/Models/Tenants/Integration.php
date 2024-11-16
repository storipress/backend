<?php

namespace App\Models\Tenants;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Webmozart\Assert\Assert;

/**
 * App\Models\Tenants\Integration
 *
 * @phpstan-type FacebookConfiguration array{
 *     user_id: string,
 *     name: string,
 *     scopes: array<int, string>,
 *     access_token: string,
 *     pages: array<string, array{
 *         page_id: string,
 *         name: string,
 *         thumbnail: string,
 *         access_token: string,
 *     }>,
 * }
 * @phpstan-type TwitterConfiguration array{
 *     user_id: string,
 *     name: string,
 *     usernmae: string,
 *     thumbnail: string,
 *     scopes: array<int, string>,
 *     expires_on: int,
 *     access_token: string,
 *     refresh_token: string,
 * }
 *
 * @property string $key
 * @property array $data
 * @property array|null $internals
 * @property \Illuminate\Support\Carbon|null $activated_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read array<mixed>|null $configuration
 *
 * @method static Builder|Integration activated()
 * @method static \Database\Factories\Tenants\IntegrationFactory factory($count = null, $state = [])
 * @method static Builder|Integration newModelQuery()
 * @method static Builder|Integration newQuery()
 * @method static Builder|Integration query()
 *
 * @mixin \Eloquent
 */
class Integration extends Entity
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'integrations';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'key';

    /**
     * The "type" of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'data' => 'array',
        'internals' => 'array',
        'activated_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * @var string[]
     */
    protected array $renames = [
        'linkedin' => 'linkedIn',
    ];

    /**
     * @var string[]
     */
    protected array $ignores = [
        'wordpress',
    ];

    public function revoke(): bool
    {
        return $this->update([
            'data' => [],
            'internals' => null,
            'activated_at' => null,
        ]);
    }

    /**
     * Scope a query to only include activated integrations.
     *
     * @param  Builder<Integration>  $query
     * @return Builder<Integration>
     */
    public function scopeActivated(Builder $query): Builder
    {
        return $query->whereNotNull('activated_at');
    }

    public static function isShopifyActivate(): bool
    {
        $shopify = Integration::find('shopify');

        if ($shopify === null) {
            return false;
        }

        return $shopify->activated_at !== null
            && Arr::get($shopify->internals ?: [], 'domain') !== null;
    }

    /**
     * @return array<mixed>|null
     */
    public function getConfigurationAttribute(): ?array
    {
        if (in_array($this->key, $this->ignores)) {
            return null;
        }

        $class = $this->getMakerClass();

        if (! class_exists($class)) {
            return null;
        }

        $maker = new $class($this->internals);

        Assert::isInstanceOf($maker, \App\Maker\Integrations\Integration::class);

        if (! $maker->validate()) {
            return null;
        }

        $data = $maker->configuration();

        if (! empty($data)) {
            $data['type'] = $this->renames[$this->key] ?? $this->key;
        }

        $data['key'] = $this->key;

        return $data;
    }

    public function postValidate(): bool
    {
        $class = $this->getMakerClass();

        $attributes = $this->attributes;

        $attributes['internals'] = $this->internals;

        $attributes['data'] = $this->data;

        $maker = new $class($attributes);

        Assert::isInstanceOf($maker, \App\Maker\Integrations\Integration::class);

        return $maker->postValidate();
    }

    public function getMakerClass(): string
    {
        $key = $this->renames[$this->key] ?? $this->key;

        return sprintf('App\\Maker\\Integrations\\%s', Str::studly($key));
    }

    /**
     * Reset the integration to default state.
     */
    public function reset(): bool
    {
        return $this->update([
            'data' => [],
            'internals' => null,
            'activated_at' => null,
            'updated_at' => now(),
        ]);
    }
}
