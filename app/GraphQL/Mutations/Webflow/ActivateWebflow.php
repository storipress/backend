<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\Webflow;

use App\Models\Tenants\Integrations\Webflow;

final readonly class ActivateWebflow
{
    /**
     * @param  array{}  $args
     */
    public function __invoke(null $_, array $args): bool
    {
        $now = now();

        return Webflow::retrieve()->update([
            'activated_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
