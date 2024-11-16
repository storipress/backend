<?php

namespace App\GraphQL\Mutations\Stage;

use App\Exceptions\BadRequestHttpException;
use App\Exceptions\InternalServerErrorHttpException;
use App\Exceptions\NotFoundHttpException;
use App\GraphQL\Mutations\Mutation;
use App\Models\Tenants\Article;
use App\Models\Tenants\Stage;
use App\Models\Tenants\UserActivity;
use Exception;

use function Sentry\captureException;

final class DeleteStage extends Mutation
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

        $default = Stage::whereDefault(true)->first();

        if (is_null($default)) {
            throw new InternalServerErrorHttpException();
        }

        if ($stage->default || $stage->ready) {
            throw new BadRequestHttpException();
        }

        try {
            /** @var int|null $order */
            $order = Article::whereStageId($default->getKey())->max('order');

            $articles = Article::whereStageId($stage->getKey());

            if (is_int($order) && $order > 0) {
                $articles->increment('order', $order);
            }

            $articles->update(['stage_id' => $default->getKey()]);

            $stage->delete();

            $default->articles()->chunk(500, fn ($articles) => $articles->searchable());
        } catch (Exception $e) {
            captureException($e);

            throw new InternalServerErrorHttpException();
        }

        UserActivity::log(
            name: 'stage.delete',
            subject: $stage,
        );

        return $stage;
    }
}
