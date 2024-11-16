<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\WordPress;

use App\Enums\WordPress\OptionalFeature;
use App\Models\Tenants\Integrations\WordPress;

final readonly class OptOutWordPressFeature
{
    /**
     * @param array{
     *     key: OptionalFeature,
     * } $args
     */
    public function __invoke(null $_, array $args): bool
    {
        WordPress::retrieve()->config->update([
            'feature' => [
                $args['key']->value => false,
            ],
        ]);

        return true;
    }
}
