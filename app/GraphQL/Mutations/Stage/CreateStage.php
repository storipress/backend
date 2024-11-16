<?php

namespace App\GraphQL\Mutations\Stage;

use App\Exceptions\BadRequestHttpException;
use App\GraphQL\Mutations\Mutation;
use App\Models\Tenants\Stage;
use App\Models\Tenants\UserActivity;
use Exception;
use Illuminate\Support\Arr;

final class CreateStage extends Mutation
{
    /**
     * @param  array<string, mixed>  $args
     *
     * @throws Exception
     */
    public function __invoke($_, array $args): Stage
    {
        $this->authorize('write', Stage::class);

        /** @var Stage|null $target */
        $target = Stage::find($args['after']);

        if (is_null($target)) {
            throw new BadRequestHttpException();
        }

        $stage = Stage::create(Arr::except($args, ['after']));

        $stage->moveAfter($target);

        $stage->refresh();

        UserActivity::log(
            name: 'stage.create',
            subject: $stage,
        );

        return $stage;
    }
}
