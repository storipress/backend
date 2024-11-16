<?php

namespace App\GraphQL\Mutations\CustomField;

use App\Exceptions\NotFoundHttpException;
use App\GraphQL\Mutations\Mutation;
use App\Models\Tenants\CustomField;
use App\Models\Tenants\UserActivity;
use Illuminate\Support\Str;

class DeleteCustomField extends Mutation
{
    /**
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args): CustomField
    {
        $this->authorize('write', new CustomField());

        /** @var CustomField|null $field */
        $field = CustomField::find($args['id']);

        if ($field === null) {
            throw new NotFoundHttpException();
        }

        $field->update([
            'key' => sprintf(
                '%s-%s',
                $field->key,
                Str::lower(Str::random(6)),
            ),
        ]);

        $field->delete();

        UserActivity::log(
            name: 'custom-field.delete',
            subject: $field,
        );

        return $field;
    }
}
