<?php

namespace App\Maker\Integrations;

use Illuminate\Support\Arr;

class Twitter extends Integration
{
    /**
     * {@inheritDoc}
     */
    protected function getRules(): array
    {
        return [
            '*.name' => 'required|string',
            '*.user_id' => 'required|string',
            '*.thumbnail' => 'required|string',
        ];
    }

    /**
     * {@inheritDoc}
     */
    protected function getPostRules(): array
    {
        return [];
    }

    /**
     * {@inheritDoc}
     */
    protected function getAllowFields(): array
    {
        return [
            'name',
            'user_id',
            'thumbnail',
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function configuration(): array
    {
        // TODO: flatten the array
        /** @var array<mixed>|array{} $attribute */
        $attribute = $this->attributes[0] ?? [];

        return Arr::only($attribute, $this->getAllowFields());
    }

    /**
     * {@inheritDoc}
     */
    protected function getUpdateRules(): array
    {
        // TODO: flatten the array
        return [
            // ensure internals is not empty
            '0 => ' => 'required|array',
            '*.access_token' => 'required',
        ];
    }
}
