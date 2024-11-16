<?php

namespace App\Maker\Integrations;

class GoogleAnalytics extends Integration
{
    /**
     * {@inheritDoc}
     */
    protected function getRules(): array
    {
        return [];
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
        return [];
    }

    /**
     * {@inheritDoc}
     */
    protected function getUpdateRules(): array
    {
        return [];
    }
}
