<?php

namespace App\GraphQL\Directives;

use App\Sluggable;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgSanitizerDirective;

class TransformSlugDirective extends BaseDirective implements ArgDirective, ArgSanitizerDirective
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
directive @transformSlugDirective on INPUT_FIELD_DEFINITION
GRAPHQL;
    }

    /**
     * transform slug value
     */
    public function sanitize($argumentValue): ?string
    {
        if (!is_string($argumentValue)) {
            return null;
        }

        if (empty($argumentValue)) {
            return null;
        }

        return Sluggable::slug($argumentValue);
    }
}
