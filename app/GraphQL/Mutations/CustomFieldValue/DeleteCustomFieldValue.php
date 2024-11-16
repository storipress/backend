<?php

namespace App\GraphQL\Mutations\CustomFieldValue;

use App\Exceptions\NotFoundHttpException;
use App\GraphQL\Mutations\Mutation;
use App\Models\Tenants\CustomFieldValue;
use App\Models\Tenants\UserActivity;

class DeleteCustomFieldValue extends Mutation
{
    /**
     * @param  array{id: string}  $args
     */
    public function __invoke($_, array $args): CustomFieldValue
    {
        /** @var CustomFieldValue|null $value */
        $value = CustomFieldValue::find($args['id']);

        if ($value === null) {
            throw new NotFoundHttpException();
        }

        $value->delete();

        UserActivity::log(
            name: 'custom-field-value.delete',
            subject: $value,
        );

        return $value;
    }
}
