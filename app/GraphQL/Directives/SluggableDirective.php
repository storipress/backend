<?php

namespace App\GraphQL\Directives;

use App\Sluggable;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgSanitizerDirective;

class SluggableDirective extends BaseDirective implements ArgDirective, ArgSanitizerDirective
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
directive @sluggable on INPUT_FIELD_DEFINITION
GRAPHQL;
    }

    /**
     * transform slug value
     */
    public function sanitize($argumentValue): ?string
    {
        if (!is_not_empty_string($argumentValue)) {
            return null;
        }

        return Sluggable::slug($argumentValue);
    }
}
