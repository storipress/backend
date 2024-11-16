<?php

namespace App\GraphQL\Validators;

use App\Models\Tenants\Desk;
use Illuminate\Validation\Rule;
use Nuwave\Lighthouse\Validation\Validator;

final class UpdateDeskInputValidator extends Validator
{
    /**
     * Return the validation rules.
     *
     * @return array<string, array<int, string|Rule>>
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
                'required',
                'string',
                'max:72',
                Rule::unique(Desk::class, 'slug')->ignore($id ?? null),
            ],
        ];
    }
}
