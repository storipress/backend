<?php

namespace App\GraphQL\Mutations\CustomFieldGroup;

use App\Exceptions\InternalServerErrorHttpException;
use App\Exceptions\NotFoundHttpException;
use App\GraphQL\Mutations\Mutation;
use App\Models\Tenants\CustomFieldGroup;
use App\Models\Tenants\UserActivity;
use Illuminate\Support\Arr;

class UpdateCustomFieldGroup extends Mutation
{
    /**
     * @param  array{
     *     id: string,
     *     name?: string,
     *     description?: string,
     * }  $args
     */
    public function __invoke($_, array $args): CustomFieldGroup
    {
        $this->authorize('write', new CustomFieldGroup());

        $group = CustomFieldGroup::find($args['id']);

        if ($group === null) {
            throw new NotFoundHttpException();
        }

        $attributes = Arr::except($args, ['id']);

        if (empty($attributes)) {
            return $group;
        }

        $origin = $group->only(array_keys($attributes));

        $updated = $group->update($attributes);

        if (!$updated) {
            throw new InternalServerErrorHttpException();
        }

        $group->refresh();

        UserActivity::log(
            name: 'custom-field-group.update',
            subject: $group,
            data: [
                'old' => $origin,
                'new' => $attributes,
            ],
        );

        return $group;
    }
}
