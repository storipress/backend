<?php

declare(strict_types=1);

namespace App\GraphQL\Queries\WordPress;

use App\Models\Tenants\Integrations\WordPress;

final readonly class WordPressInfo
{
    /**
     * @param  array{}  $args
     * @return array<string, mixed>
     */
    public function __invoke(null $_, array $args): array
    {
        $wordpress = WordPress::retrieve();

        return array_merge(
            $wordpress->is_connected ? (array) $wordpress->config : [],
            [
                'activated_at' => $wordpress->activated_at,
            ],
        );
    }
}
