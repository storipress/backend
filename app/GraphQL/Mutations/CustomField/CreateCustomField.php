<?php

namespace App\GraphQL\Mutations\CustomField;

use App\Enums\CustomField\Type;
use App\Exceptions\ValidationException;
use App\GraphQL\Mutations\Mutation;
use App\Models\Tenants\CustomField;
use App\Models\Tenants\UserActivity;
use stdClass;

class CreateCustomField extends Mutation
{
    use HasCustomFieldOptions;

    /**
     * @param  array{
     *     custom_field_group_id: string,
     *     key: string,
     *     type: Type,
     *     name: string,
     *     description?: string,
     *     options?: stdClass,
     * }  $args
     *
     * @throws ValidationException
     * @throws \Illuminate\Validation\ValidationException
     */
    public function __invoke($_, array $args): CustomField
    {
        $this->authorize('write', new CustomField());

        $args['options'] = $this->validateOptions(
            $args['type'],
            (array) ($args['options'] ?? []),
        );

        $field = CustomField::create($args)->refresh();

        UserActivity::log(
            name: 'custom-field.create',
            subject: $field,
        );

        return $field;
    }
}
