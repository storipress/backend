<?php

declare(strict_types=1);

namespace App\GraphQL\Queries\Webflow;

use App\Models\Tenants\Integrations\Webflow;
use Storipress\Webflow\Objects\Site;

final readonly class WebflowSites
{
    /**
     * @param  array{}  $args
     * @return array<int, Site>
     */
    public function __invoke(null $_, array $args): array
    {
        return Webflow::retrieve()->config->raw_sites;
    }
}
