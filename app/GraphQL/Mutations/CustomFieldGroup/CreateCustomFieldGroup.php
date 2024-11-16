<?php

namespace App\GraphQL\Mutations\CustomFieldGroup;

use App\GraphQL\Mutations\Mutation;
use App\Models\Tenants\CustomFieldGroup;
use App\Models\Tenants\UserActivity;

class CreateCustomFieldGroup extends Mutation
{
    /**
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args): CustomFieldGroup
    {
        $this->authorize('write', new CustomFieldGroup());

        $group = CustomFieldGroup::create($args)->refresh();

        UserActivity::log(
            name: 'custom-field-group.create',
            subject: $group,
        );

        return $group;
    }
}
