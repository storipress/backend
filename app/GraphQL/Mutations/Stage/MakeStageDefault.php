<?php

namespace App\GraphQL\Mutations\Stage;

use App\Exceptions\InternalServerErrorHttpException;
use App\Exceptions\NotFoundHttpException;
use App\GraphQL\Mutations\Mutation;
use App\Models\Tenants\Stage;
use Illuminate\Support\Facades\DB;
use Throwable;

final class MakeStageDefault extends Mutation
{
    /**
     * @param  array<string, string>  $args
     */
    public function __invoke($_, array $args): Stage
    {
        // @deprecated
        $this->authorize('write', Stage::class);

        $stage = Stage::find($args['id']);

        if (is_null($stage)) {
            throw new NotFoundHttpException();
        }

        if ($stage->default) {
            return $stage;
        }

        try {
            DB::transaction(function () use ($stage) {
                Stage::query()
                    ->where('default', '=', true)
                    ->update(['default' => false]);

                if (! $stage->update(['default' => true])) {
                    throw new InternalServerErrorHttpException();
                }
            });
        } catch (Throwable $e) {
            throw new InternalServerErrorHttpException();
        }

        return $stage;
    }
}
