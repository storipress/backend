<?php

namespace App\Maker\Integrations;

class Slack extends Integration
{
    /**
     * {@inheritDoc}
     */
    protected function getRules(): array
    {
        return [
            'id' => 'required|string',
            'name' => 'required|string',
            'thumbnail' => 'required|string',
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
            'thumbnail',
        ];
    }

    /**
     * {@inheritDoc}
     */
    protected function getUpdateRules(): array
    {
        return [
            'bot_access_token' => 'required',
        ];
    }
}
