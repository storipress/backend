<?php

namespace App\Maker\Integrations;

class Webflow extends Integration
{
    protected function getRules(): array
    {
        return [
            'name' => 'required|string',
            'email' => 'required|email',
            'user_id' => 'required|string',
            'collections' => 'array',
        ];
    }

    protected function getPostRules(): array
    {
        return [
            'internals.access_token' => 'required|string',
            'internals.collections.*.id' => 'required|string',
        ];
    }

    protected function getAllowFields(): array
    {
        return [
            'name',
            'email',
            'user_id',
            'v2',
            'expired',
            'collections',
        ];
    }

    protected function getUpdateRules(): array
    {
        return [
            'access_token' => 'required',
        ];
    }
}
