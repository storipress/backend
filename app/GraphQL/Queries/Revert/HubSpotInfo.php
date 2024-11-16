<?php

declare(strict_types=1);

namespace App\GraphQL\Queries\Revert;

use App\Models\Tenants\Integration;

final readonly class HubSpotInfo
{
    /**
     * @param  array{}  $args
     * @return array<string, mixed>
     */
    public function __invoke(null $_, array $args): array
    {
        $hubspot = Integration::where('key', '=', 'hubspot')->first();

        return [
            'activated_at' => $hubspot?->activated_at,
        ];
    }
}
