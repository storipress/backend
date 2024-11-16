<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\Webflow;

use App\Models\Tenants\Integrations\Webflow;

final readonly class DeactivateWebflow
{
    /**
     * @param  array{}  $args
     */
    public function __invoke(null $_, array $args): bool
    {
        return Webflow::retrieve()->update([
            'activated_at' => null,
            'updated_at' => now(),
        ]);
    }
}
