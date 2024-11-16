<?php

namespace App\GraphQL\Mutations\CustomFieldGroup;

use App\Exceptions\NotFoundHttpException;
use App\GraphQL\Mutations\Mutation;
use App\Models\Tenants\CustomFieldGroup;
use App\Models\Tenants\UserActivity;
use Illuminate\Support\Str;

class DeleteCustomFieldGroup extends Mutation
{
    /**
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args): CustomFieldGroup
    {
        $this->authorize('write', new CustomFieldGroup());

        /** @var CustomFieldGroup|null $group */
        $group = CustomFieldGroup::find($args['id']);

        if ($group === null) {
            throw new NotFoundHttpException();
        }

        $group->update([
            'key' => sprintf(
                '%s-%s',
                $group->key,
                Str::lower(Str::random(6)),
            ),
        ]);

        $group->delete();

        UserActivity::log(
            name: 'custom-field-group.delete',
            subject: $group,
        );

        return $group;
    }
}
