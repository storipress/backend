<?php

namespace App\Maker\Integrations;

use Illuminate\Support\Arr;

class Facebook extends Integration
{
    /**
     * {@inheritDoc}
     */
    protected function getRules(): array
    {
        return [
            '*.page_id' => 'required|string',
            '*.name' => 'required|string',
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
            'page_id',
            'name',
            'thumbnail',
        ];
    }

    public function configuration(): array
    {
        return [
            'pages' => array_filter(Arr::map($this->attributes, function ($attribute) {
                return Arr::only($attribute, $this->getAllowFields());
            })),
        ];
    }

    protected function getUpdateRules(): array
    {
        return [
            // ensure internals is not empty
            '0 => ' => 'required|array',
            '*.access_token' => 'required',
        ];
    }
}
