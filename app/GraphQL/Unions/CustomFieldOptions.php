<?php

namespace App\GraphQL\Unions;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\TypeRegistry;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class CustomFieldOptions
{
    public function __construct(
        protected TypeRegistry $typeRegistry,
    ) {
        //
    }

    /**
     * Decide which GraphQL type a resolved value has.
     *
     * @param  array<string, bool|float|int|string>|null  $rootValue  The value that was resolved by the field. Usually an Eloquent model.
     *
     * @throws DefinitionException
     */
    public function __invoke(?array $rootValue, GraphQLContext $context, ResolveInfo $resolveInfo): Type
    {
        $type = 'CustomFieldIgnoreOptions';

        if (
            $rootValue &&
            ($value = Arr::get($rootValue, 'type')) &&
            is_string($value)
        ) {
            $type = sprintf(
                'CustomField%sOptions',
                Str::studly($value),
            );
        }

        return $this->typeRegistry->get($type);
    }
}
