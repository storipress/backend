<?php

namespace App\GraphQL\Mutations\CustomFieldGroup;

use App\Enums\CustomField\GroupType;
use App\Exceptions\BadRequestHttpException;
use App\Exceptions\NotFoundHttpException;
use App\GraphQL\Mutations\Mutation;
use App\Models\Tenants\CustomFieldGroup;
use App\Models\Tenants\UserActivity;

class SyncGroupableToCustomFieldGroup extends Mutation
{
    /**
     * @param  array{
     *     id: string,
     *     target_ids: string[],
     *     detaching?: bool,
     * }  $args
     */
    public function __invoke($_, array $args): CustomFieldGroup
    {
        $this->authorize('write', new CustomFieldGroup());

        $group = CustomFieldGroup::find($args['id']);

        if ($group === null || $group->type === null) {
            throw new NotFoundHttpException();
        }

        $related = match ($group->type->value) {
            GroupType::tagMetafield()->value => 'tags',
            GroupType::deskMetafield()->value => 'desks',
            default => null,
        };

        if ($related === null) {
            throw new BadRequestHttpException();
        }

        $origin = $group->{$related}()->pluck('id');

        $group->{$related}()->sync($args['target_ids'], $args['detaching'] ?? true);

        $group->refresh();

        $new = $group->{$related}()->pluck('id');

        UserActivity::log(
            name: 'custom-field-group.groupable.sync',
            subject: $group,
            data: [
                'type' => $related,
                'detaching' => $args['detaching'] ?? true,
                'old' => $origin,
                'new' => $new,
            ],
        );

        return $group;
    }
}
