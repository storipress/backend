<?php

namespace App\GraphQL\Directives;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\Parser;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Arr;
use Laravel\Scout\Builder as ScoutBuilder;
use Nuwave\Lighthouse\OrderBy\OrderByServiceProvider;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Scout\ScoutBuilderDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgBuilderDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgDirectiveForArray;
use Nuwave\Lighthouse\Support\Contracts\ArgManipulator;
use Nuwave\Lighthouse\Support\Traits\GeneratesColumnsEnum;

/**
 * @template TModel of QueryBuilder|EloquentBuilder
 */
class SearchOrderByDirective extends BaseDirective implements ArgBuilderDirective, ArgDirectiveForArray, ArgManipulator, ScoutBuilderDirective
{
    use GeneratesColumnsEnum;

    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Sort a result list by one or more given columns.
"""
directive @searchOrderBy(
    """
    Restrict the allowed column names to a well-defined list.
    This improves introspection capabilities and security.
    Mutually exclusive with the `columnsEnum` argument.
    Only used when the directive is added on an argument.
    """
    columns: [String!]

    """
    Use an existing enumeration type to restrict the allowed columns to a predefined list.
    This allowes you to re-use the same enum for multiple fields.
    Mutually exclusive with the `columns` argument.
    Only used when the directive is added on an argument.
    """
    columnsEnum: String

    """
    The database column for which the order by clause will be applied on.
    Only used when the directive is added on a field.
    """
    column: String

    """
    The direction of the order by clause.
    Only used when the directive is added on a field.
    """
    direction: OrderByDirection = ASC
) on ARGUMENT_DEFINITION | FIELD_DEFINITION
GRAPHQL;
    }

    /**
     * @param  TModel  $builder
     * @param  array<array<string, mixed>>  $value
     * @return TModel
     */
    public function handleBuilder($builder, $value): EloquentBuilder|QueryBuilder
    {
        foreach ($value as $orderByClause) {
            /** @var string $order */
            $order = Arr::pull($orderByClause, 'order');

            /** @var string $column */
            $column = Arr::pull($orderByClause, 'column');

            $builder->orderBy($column, $order);
        }

        return $builder;
    }

    /**
     * @param  array<array<string, mixed>>  $value
     */
    public function handleScoutBuilder(ScoutBuilder $builder, $value): ScoutBuilder
    {
        foreach ($value as $orderByClause) {
            /** @var string $order */
            $order = Arr::pull($orderByClause, 'order');

            /** @var string $column */
            $column = Arr::pull($orderByClause, 'column');

            $builder->orderBy($column, $order);
        }

        return $builder;
    }

    /**
     * @throws \Nuwave\Lighthouse\Exceptions\DefinitionException
     */
    public function manipulateArgDefinition(
        DocumentAST &$documentAST,
        InputValueDefinitionNode &$argDefinition,
        FieldDefinitionNode &$parentField,
        ObjectTypeDefinitionNode|InterfaceTypeDefinitionNode &$parentType,
    ): void {
        if (!$this->hasAllowedColumns()) {
            $argDefinition->type = Parser::typeReference('[' . OrderByServiceProvider::DEFAULT_ORDER_BY_CLAUSE . '!]');

            return;
        }

        $qualifiedOrderByPrefix = ASTHelper::qualifiedArgType($argDefinition, $parentField, $parentType);

        $allowedColumnsTypeName = $this->generateColumnsEnum($documentAST, $argDefinition, $parentField, $parentType);

        $restrictedOrderByName = $qualifiedOrderByPrefix . 'OrderByClause';
        $argDefinition->type = Parser::typeReference('[' . $restrictedOrderByName . '!]');

        $documentAST->setTypeDefinition(
            OrderByServiceProvider::createOrderByClauseInput(
                $restrictedOrderByName,
                "Order by clause for {$parentType->name->value}.{$parentField->name->value}.{$argDefinition->name->value}.",
                $allowedColumnsTypeName,
            ),
        );
    }
}
