<?php

namespace App\GraphQL\Extends;

use App\Exceptions\ValidationException;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Container\Container;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Nuwave\Lighthouse\Execution\Arguments\ArgumentSet;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Validation\RulesGatherer;
use Nuwave\Lighthouse\Validation\ValidateDirective as BaseValidateDirective;

class ValidateDirective extends BaseValidateDirective implements FieldMiddleware
{
    public function handleField(FieldValue $fieldValue): void
    {
        $fieldValue->addArgumentSetTransformer(static function (ArgumentSet $argumentSet, ResolveInfo $resolveInfo): ArgumentSet {
            $rulesGatherer = new RulesGatherer($argumentSet);
            $validationFactory = Container::getInstance()->make(ValidationFactory::class);
            $validator = $validationFactory->make(
                $argumentSet->toArray(),
                $rulesGatherer->rules,
                $rulesGatherer->messages,
                $rulesGatherer->attributes,
            );
            if ($validator->fails()) {
                $path = implode('.', $resolveInfo->path);

                throw new ValidationException("Validation failed for the field [{$path}].", $validator);
            }

            return $argumentSet;
        });
    }
}
