<?php

namespace App\GraphQL\Directives;

use App\Exceptions\BadRequestHttpException;
use App\Models\Tenant;
use Illuminate\Support\Facades\Route;
use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class TenantOnlyFieldDirective extends BaseDirective implements FieldMiddleware
{
    /**
     * Formal directive specification in schema definition language (SDL).
     *
     * @see https://spec.graphql.org/draft/#sec-Type-System.Directives
     *
     * This must contain a single directive definition, but can also contain
     * auxiliary types, such as enum definitions for directive arguments.
     *
     * @retrun string
     */
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Restrict the field can only be retrieved from tenant scope.
"""
directive @tenantOnlyField repeatable on FIELD_DEFINITION
GRAPHQL;
    }

    /**
     * Wrap around the final field resolver.
     */
    public function handleField(FieldValue $fieldValue): void
    {
        $fieldValue->wrapResolver(
            fn (callable $previousResolver) => function (mixed $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($previousResolver) {
                $route = Route::current()?->getName() ?: '';

                if (str_contains($route, 'graphql') && $route !== 'graphql') {
                    throw new BadRequestHttpException();
                }

                /** @var Tenant|null $tenant */
                $tenant = tenant();

                if ($tenant !== null && $tenant->initialized === false) {
                    throw new BadRequestHttpException();
                }

                return $previousResolver($root, $args, $context, $resolveInfo);
            });
    }
}
