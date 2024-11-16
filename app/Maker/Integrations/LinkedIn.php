<?php

namespace App\Maker\Integrations;

class LinkedIn extends Integration
{
    /**
     * {@inheritDoc}
     */
    protected function getRules(): array
    {
        return [
            'id' => 'required|string',
            'name' => 'required|string',
            'email' => 'required|email',
            'thumbnail' => 'nullable|string',
            'authors' => 'required|array',
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
            'thumbnail',
            'authors',
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
