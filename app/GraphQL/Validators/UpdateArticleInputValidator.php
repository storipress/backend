<?php

namespace App\GraphQL\Validators;

use Illuminate\Validation\Rule;
use Nuwave\Lighthouse\Validation\Validator;

final class UpdateArticleInputValidator extends Validator
{
    /**
     * Return the validation rules.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        if ($this->args->has('id')) {
            $id = $this->args->arguments['id']->value;
        }

        return [
            'slug' => [
                'bail',
                'sometimes',
                'nullable',
                'string',
                'max:230',
                Rule::unique('articles', 'slug')->ignore($id ?? null),
            ],
        ];
    }
}
