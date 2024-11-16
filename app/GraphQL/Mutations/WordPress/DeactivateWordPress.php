<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\WordPress;

use App\Models\Tenants\Integrations\WordPress;

final readonly class DeactivateWordPress
{
    /**
     * @param  array{}  $args
     */
    public function __invoke(null $_, array $args): bool
    {
        return WordPress::retrieve()->update([
            'activated_at' => null,
            'updated_at' => now(),
        ]);
    }
}
