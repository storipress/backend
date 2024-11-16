<?php

namespace App\GraphQL\Unions;

use App\Exceptions\InternalServerErrorHttpException;
use App\Models\Tenants\CustomFieldValue as CustomFieldValueModel;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Illuminate\Support\Str;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\TypeRegistry;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class CustomFieldValue
{
    public function __construct(
        protected TypeRegistry $typeRegistry,
    ) {
        //
    }

    /**
     * Decide which GraphQL type a resolved value has.
     *
     * @param  CustomFieldValueModel  $rootValue  The value that was resolved by the field. Usually an Eloquent model.
     *
     * @throws DefinitionException
     */
    public function __invoke(CustomFieldValueModel $rootValue, GraphQLContext $context, ResolveInfo $resolveInfo): Type
    {
        $type = $rootValue->type?->value ?: $rootValue->customField?->type?->value;

        if (! is_string($type)) {
            throw new InternalServerErrorHttpException();
        }

        $name = sprintf('CustomField%sValue', Str::studly($type));

        return $this->typeRegistry->get($name);
    }
}
