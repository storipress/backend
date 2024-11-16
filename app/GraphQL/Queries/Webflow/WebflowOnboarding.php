<?php

declare(strict_types=1);

namespace App\GraphQL\Queries\Webflow;

use App\Models\Tenants\Integrations\Configurations\WebflowConfiguration;
use App\Models\Tenants\Integrations\Webflow;

/**
 * @phpstan-import-type WebflowOnboarding from WebflowConfiguration as Onboarding
 */
final readonly class WebflowOnboarding
{
    /**
     * @param  array{}  $args
     * @return Onboarding
     */
    public function __invoke(null $_, array $args): array
    {
        return Webflow::retrieve()->config->onboarding;
    }
}
