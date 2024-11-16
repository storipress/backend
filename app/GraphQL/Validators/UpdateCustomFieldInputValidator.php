<?php

namespace App\GraphQL\Validators;

use App\Exceptions\BadRequestHttpException;
use App\Models\Tenant;
use App\Models\Tenants\CustomField;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use Nuwave\Lighthouse\Validation\Validator;
use Webmozart\Assert\Assert;

class UpdateCustomFieldInputValidator extends Validator
{
    /**
     * Return the validation rules.
     *
     * @return array<string, array<string|Unique>>
     */
    public function rules(): array
    {
        $tenant = tenant();

        Assert::isInstanceOf($tenant, Tenant::class);

        if ($groupId = $this->arg('custom_field_group_id')) {
            $unique = Rule::unique('custom_fields', 'key')
                ->where('custom_field_group_id', $groupId); // @phpstan-ignore-line
        } elseif ($fieldId = $this->arg('id')) {
            $field = CustomField::find($fieldId);

            Assert::isInstanceOf($field, CustomField::class);

            $unique = Rule::unique('custom_fields', 'key')
                ->where('custom_field_group_id', $field->custom_field_group_id)
                ->ignore($field->id);
        } else {
            throw new BadRequestHttpException();
        }

        return [
            'key' => [
                'bail',
                'sometimes',
                'required',
                'between:3,32',
                'regex:/^[a-z_][a-z0-9_]*$/',
                $unique,
            ],
        ];
    }
}
