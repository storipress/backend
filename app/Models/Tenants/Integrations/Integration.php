<?php

declare(strict_types=1);

namespace App\Models\Tenants\Integrations;

use App\Models\Tenants\Integration as BaseIntegration;
use App\Models\Tenants\Integrations\Configurations\Configuration;
use App\Models\Tenants\Integrations\Configurations\GeneralConfiguration;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Str;

/**
 * @template T
 */
abstract class Integration extends BaseIntegration
{
    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        parent::boot();

        static::addGlobalScope('integration-key', function (Builder $builder) {
            $class = class_basename($builder->getModel());

            $key = Str::lower($class);

            $builder->where('key', '=', $key);
        });
    }

    public static function retrieve(bool $lock = false): static
    {
        $builder = new static(); // @phpstan-ignore-line

        if ($lock) {
            $builder = $builder->lockForUpdate();
        }

        return $builder->sole(); // @phpstan-ignore-line
    }

    /**
     * @return Attribute<T, void>
     */
    protected function config(): Attribute
    {
        $class = sprintf(
            'App\\Models\\Tenants\\Integrations\\Configurations\\%sConfiguration',
            class_basename($this),
        );

        if (! is_subclass_of($class, Configuration::class)) {
            $class = GeneralConfiguration::class;
        }

        return Attribute::make(
            get: fn () => $class::from($this),
        );
    }

    abstract public function getIsActivatedAttribute(): bool;
}
