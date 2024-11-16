<?php

declare(strict_types=1);

namespace App\Models\Tenants\Integrations\Configurations;

use App\Events\Entity\Integration\IntegrationConfigurationUpdated;
use App\Models\Tenants\Integrations\Integration;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Throwable;

abstract class Configuration
{
    /**
     * @param  Integration<*>  $model
     * @param  array<non-empty-string, mixed>  $raw
     *
     * @noinspection PhpVarTagWithoutVariableNameInspection
     */
    final public function __construct(
        protected Integration $model,
        array $raw,
    ) {
        foreach ($raw as $key => $value) {
            $this->{$key} = $value;
        }
    }

    /**
     * @param  array<non-empty-string, mixed>  $attributes
     *
     * @throws Throwable
     */
    public function update(array $attributes): static
    {
        DB::transaction(function () use ($attributes) {
            $integration = $this->model->retrieve(true);

            $configuration = $integration->internals ?: [];

            $original = Arr::dot($configuration);

            foreach ($attributes as $key => $value) {
                if (!is_iterable($value)) {
                    Arr::set($configuration, $key, $value);
                } else {
                    $wrapped = Arr::dot($value, sprintf('%s.', $key));

                    foreach ($wrapped as $innerKey => $innerValue) {
                        Arr::set($configuration, $innerKey, $innerValue);
                    }
                }
            }

            $integration->update([
                'internals' => $configuration,
                'updated_at' => now(),
            ]);

            if (!$integration->wasChanged('internals')) {
                return;
            }

            $latest = Arr::dot($configuration);

            $changes = [];

            foreach ($original as $key => $value) {
                if (!array_key_exists($key, $latest)) {
                    $changes[$key] = null;
                } elseif ($latest[$key] !== $value) {
                    $changes[$key] = $latest[$key];
                }
            }

            IntegrationConfigurationUpdated::dispatch(
                tenant_or_fail()->id,
                $integration->key,
                Arr::undot($changes),
                Arr::undot(Arr::only($original, array_keys($changes))),
            );
        });

        return $this;
    }

    /**
     * @param  Integration<Configuration>  $integration
     */
    abstract public static function from(Integration $integration): static;
}
