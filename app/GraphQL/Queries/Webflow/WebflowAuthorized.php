<?php

declare(strict_types=1);

namespace App\GraphQL\Queries\Webflow;

use App\Models\Tenants\Integrations\Webflow;

final readonly class WebflowAuthorized
{
    /**
     * @param  array{}  $args
     */
    public function __invoke(null $_, array $args): bool
    {
        $config = Webflow::retrieve()->internals ?: [];

        if (!($config['v2'] ?? false)) {
            return false;
        }

        if ($config['expired'] ?? false) {
            return false;
        }

        return is_not_empty_string($config['access_token'] ?? '');
    }
}
