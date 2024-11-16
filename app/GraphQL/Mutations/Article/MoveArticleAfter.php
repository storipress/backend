<?php

namespace App\GraphQL\Mutations\Article;

use App\Exceptions\BadRequestHttpException;
use App\Exceptions\NotFoundHttpException;
use App\Models\Tenants\Article;
use App\Models\Tenants\UserActivity;
use Exception;

class MoveArticleAfter
{
    /**
     * @param  array<string, mixed>  $args
     *
     * @throws Exception
     */
    public function __invoke($_, array $args): bool
    {
        /** @var Article|null $article */
        $article = Article::find($args['id']);

        /** @var Article|null $target */
        $target = Article::find($args['target_id']);

        if ($article === null || $target === null) {
            throw new NotFoundHttpException();
        }

        if ($article->stage_id !== $target->stage_id) {
            throw new BadRequestHttpException();
        }

        $origin = $article->order;

        $article->moveAfter($target);

        Article::whereStageId($article->stage_id)->searchable();

        UserActivity::log(
            name: 'article.order.change',
            subject: $article,
            data: [
                'old' => $origin,
                'new' => $article->order,
            ],
        );

        return true;
    }
}
