<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\WordPress;

use App\Models\Tenants\Integrations\WordPress;

final readonly class ActivateWordPress
{
    /**
     * @param  array{}  $args
     */
    public function __invoke(null $_, array $args): bool
    {
        $now = now();

        return WordPress::retrieve()->update([
            'activated_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
