<?php

namespace App\GraphQL\Directives;

use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgSanitizerDirective;

class CentralDirective extends BaseDirective implements ArgDirective, ArgSanitizerDirective
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
directive @central on INPUT_FIELD_DEFINITION
GRAPHQL;
    }

    /**
     * Remove whitespace from the beginning and end of a given input.
     */
    public function sanitize($argumentValue): mixed
    {
        config(['database.default' => config('tenancy.database.central_connection')]);

        return $argumentValue;
    }
}
