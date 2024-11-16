<?php

declare(strict_types=1);

namespace App\Models\Tenants\Integrations\Configurations;

use App\Models\Tenants\Integrations\Integration;

class GeneralConfiguration extends Configuration
{
    /**
     * @param  Integration<GeneralConfiguration>  $integration
     */
    public static function from(Integration $integration): static
    {
        return new static($integration, []);
    }
}
