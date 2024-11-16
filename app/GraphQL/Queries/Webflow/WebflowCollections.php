<?php

declare(strict_types=1);

namespace App\GraphQL\Queries\Webflow;

use App\Models\Tenants\Integrations\Webflow;
use Storipress\Webflow\Objects\Collection;

final readonly class WebflowCollections
{
    /**
     * @param  array{}  $args
     * @return array<int, Collection>
     */
    public function __invoke(null $_, array $args): array
    {
        return Webflow::retrieve()->config->raw_collections;
    }
}
