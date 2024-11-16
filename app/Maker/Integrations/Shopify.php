<?php

namespace App\Maker\Integrations;

class Shopify extends Integration
{
    /**
     * {@inheritDoc}
     */
    protected function getRules(): array
    {
        return [
            'id' => 'required|int',
            'name' => 'required|string',
            'domain' => 'required|string',
            'myshopify_domain' => 'required|string',
            'prefix' => 'required|string',
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
            'id',
            'name',
            'email',
            'domain',
            'myshopify_domain',
            'prefix',
        ];
    }

    /**
     * {@inheritDoc}
     */
    protected function getUpdateRules(): array
    {
        return [
            'access_token' => 'required',
        ];
    }
}
