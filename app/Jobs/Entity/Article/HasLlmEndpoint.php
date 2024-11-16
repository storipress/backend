<?php

declare(strict_types=1);

namespace App\Jobs\Entity\Article;

trait HasLlmEndpoint
{
    public function llm(): string
    {
        if (app()->isProduction()) {
            return 'gpt-assistant-v2.storipress.workers.dev';
        }

        return 'gpt-assistant-v2-staging.storipress.workers.dev';
    }
}
