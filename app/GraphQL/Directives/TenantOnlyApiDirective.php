<?php

namespace App\GraphQL\Directives;

use App\Exceptions\NotFoundHttpException;
use App\Models\Tenant;
use GraphQL\Language\AST\TypeDefinitionNode;
use GraphQL\Language\AST\TypeExtensionNode;
use Illuminate\Support\Facades\Route;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Nuwave\Lighthouse\Support\Contracts\TypeExtensionManipulator;
use Nuwave\Lighthouse\Support\Contracts\TypeManipulator;

class TenantOnlyApiDirective extends BaseDirective implements FieldMiddleware, TypeExtensionManipulator, TypeManipulator
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
Restrict the target API can only be called from tenant scope.
"""
directive @tenantOnlyApi repeatable on FIELD_DEFINITION | OBJECT
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
                    throw new NotFoundHttpException();
                }

                /** @var Tenant|null $tenant */
                $tenant = tenant();

                if ($tenant !== null && $tenant->initialized === false) {
                    throw new NotFoundHttpException();
                }

                return $previousResolver($root, $args, $context, $resolveInfo);
            });
    }

    /**
     * Apply manipulations from a type definition node.
     *
     *
     * @throws DefinitionException
     */
    public function manipulateTypeDefinition(DocumentAST &$documentAST, TypeDefinitionNode &$typeDefinition): void
    {
        ASTHelper::addDirectiveToFields($this->directiveNode, $typeDefinition);
    }

    /**
     * Apply manipulations from a type extension node.
     *
     *
     * @throws DefinitionException
     */
    public function manipulateTypeExtension(DocumentAST &$documentAST, TypeExtensionNode &$typeExtension): void
    {
        ASTHelper::addDirectiveToFields($this->directiveNode, $typeExtension);
    }
}
