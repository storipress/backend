<?php

namespace App\Exceptions;

use GraphQL\Error\ProvidesExtensions;
use Illuminate\Contracts\Validation\ImplicitRule;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ClosureValidationRule;
use Nuwave\Lighthouse\Exceptions\ValidationException as BaseValidationException;
use Webmozart\Assert\Assert;

class ValidationException extends BaseValidationException implements ProvidesExtensions
{
    public function getExtensions(): array
    {
        $fields = $this->validator->failed();

        $errors = $this->validator->errors();

        $messages = [];

        foreach ($fields as $key => $rules) {
            $field = Str::remove('input.', $key);

            $content = array_map(
                function (string $name) use ($errors, $key): string {
                    if (
                        $name === ClosureValidationRule::class ||
                        (class_exists($name) && in_array(ImplicitRule::class, class_implements($name) ?: []))
                    ) {
                        $name = Arr::first($errors->get($key));

                        Assert::stringNotEmpty($name);
                    }

                    return Str::lower($name);
                },
                array_keys($rules),
            );

            $messages[$field] = $content;
        }

        return [
            self::KEY => $messages,
        ];
    }
}
