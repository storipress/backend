<?php

namespace App\GraphQL\Mutations\CustomField;

use App\Exceptions\InternalServerErrorHttpException;
use App\Exceptions\NotFoundHttpException;
use App\Exceptions\ValidationException;
use App\GraphQL\Mutations\Mutation;
use App\Models\Tenants\CustomField;
use App\Models\Tenants\UserActivity;
use Illuminate\Support\Arr;
use stdClass;

class UpdateCustomField extends Mutation
{
    use HasCustomFieldOptions;

    /**
     * @param  array{
     *     id: string,
     *     name?: string,
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

        $field = CustomField::find($args['id']);

        if ($field === null || $field->type === null) {
            throw new NotFoundHttpException();
        }

        $attributes = Arr::except($args, ['id']);

        if (empty($attributes)) {
            return $field;
        }

        if (isset($attributes['options'])) {
            $attributes['options'] = $this->validateOptions(
                $field->type, // @phpstan-ignore-line
                (array) $attributes['options'],
            );
        }

        $origin = $field->only(array_keys($attributes));

        $updated = $field->update($attributes);

        if (!$updated) {
            throw new InternalServerErrorHttpException();
        }

        $field->refresh();

        UserActivity::log(
            name: 'custom-field.update',
            subject: $field,
            data: [
                'old' => $origin,
                'new' => $attributes,
            ],
        );

        return $field;
    }
}
