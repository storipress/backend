<?php

namespace App\GraphQL\Mutations\Integration;

use App\Exceptions\InternalServerErrorHttpException;
use App\GraphQL\Mutations\Mutation;
use App\Models\Tenants\Integration;
use Webmozart\Assert\Assert;

abstract class IntegrationMutation extends Mutation
{
    protected Integration $integration;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(string $key, array $data): Integration
    {
        $integration = $this->getIntegration($key);

        if (! $integration->exists) {
            $integration->data = [];
        }

        $updated = $integration->fill($data)->save();

        if (! $updated) {
            throw new InternalServerErrorHttpException();
        }

        return $integration;
    }

    public function validate(string $key): bool
    {
        $integration = $this->getIntegration($key);

        $class = $integration->getMakerClass();

        $maker = new $class($integration->internals);

        Assert::isInstanceOf($maker, \App\Maker\Integrations\Integration::class);

        return $maker->updateValidate();
    }

    protected function getIntegration(string $key): Integration
    {
        if (! isset($this->integration)) {
            $this->integration = Integration::firstOrNew(
                compact('key'),
            );
        }

        return $this->integration;
    }
}
