<?php

declare(strict_types=1);

namespace App\GraphQL\Queries\WordPress;

use App\Models\Tenants\Integrations\WordPress;

final readonly class WordPressAuthorized
{
    /**
     * @param  array{}  $args
     */
    public function __invoke(null $_, array $args): bool
    {
        return WordPress::retrieve()->is_connected;
    }
}
