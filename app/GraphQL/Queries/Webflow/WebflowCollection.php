<?php

declare(strict_types=1);

namespace App\GraphQL\Queries\Webflow;

use App\Enums\Webflow\CollectionType;
use App\Models\Tenants\Integrations\Configurations\WebflowConfiguration;
use App\Models\Tenants\Integrations\Webflow;

/**
 * @phpstan-import-type WebflowCollection from WebflowConfiguration as Collection
 */
final readonly class WebflowCollection
{
    /**
     * @param  array{
     *     type: CollectionType,
     * }  $args
     * @return Collection|null
     */
    public function __invoke(null $_, array $args): ?array
    {
        return Webflow::retrieve()->config->collections[$args['type']->value] ?? null;
    }
}
