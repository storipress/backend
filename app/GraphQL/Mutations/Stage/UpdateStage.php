<?php

namespace App\GraphQL\Mutations\Stage;

use App\Exceptions\InternalServerErrorHttpException;
use App\Exceptions\NotFoundHttpException;
use App\GraphQL\Mutations\Mutation;
use App\Models\Tenants\Stage;
use App\Models\Tenants\UserActivity;
use Illuminate\Support\Arr;

final class UpdateStage extends Mutation
{
    /**
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args): Stage
    {
        $this->authorize('write', Stage::class);

        /** @var Stage|null $stage */
        $stage = Stage::find($args['id']);

        if (is_null($stage)) {
            throw new NotFoundHttpException();
        }

        $attributes = Arr::except($args, ['id']);

        $origin = $stage->only(array_keys($attributes));

        $updated = $stage->update($attributes);

        if (! $updated) {
            throw new InternalServerErrorHttpException();
        }

        UserActivity::log(
            name: 'stage.update',
            subject: $stage,
            data: [
                'old' => $origin,
                'new' => $attributes,
            ],
        );

        return $stage;
    }
}
