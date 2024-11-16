<?php

namespace App\GraphQL\Validators;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use Nuwave\Lighthouse\Validation\Validator;

class UpdateCustomFieldGroupInputValidator extends Validator
{
    /**
     * Return the validation rules.
     *
     * @return array<string, array<string|Unique>>
     */
    public function rules(): array
    {
        return [
            'key' => [
                'bail',
                'sometimes',
                'required',
                'between:3,32',
                'regex:/^[a-z_][a-z0-9_]*$/',
                Rule::unique('custom_field_groups', 'key')
                    ->ignore($this->arg('id')),
            ],
        ];
    }
}
