<?php

declare(strict_types=1);

namespace App\GraphQL\Queries\Webflow;

use App\Models\Tenants\Integrations\Webflow;

final readonly class WebflowInfo
{
    /**
     * @param  array{}  $args
     * @return array<string, mixed>
     */
    public function __invoke(null $_, array $args): array
    {
        $webflow = Webflow::retrieve();

        return array_merge(
            $webflow->is_connected ? (array) $webflow->config : [],
            [
                'activated_at' => $webflow->activated_at,
            ],
        );
    }
}
