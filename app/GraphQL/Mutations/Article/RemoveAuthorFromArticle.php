<?php

namespace App\GraphQL\Mutations\Article;

use App\Events\Entity\Desk\DeskUserRemoved;
use App\Exceptions\ErrorCode;
use App\Exceptions\HttpException;
use App\Exceptions\NotFoundHttpException;
use App\Models\Tenant;
use App\Models\Tenants\Article;
use App\Models\Tenants\UserActivity;

final class RemoveAuthorFromArticle extends ArticleMutation
{
    /**
     * @param  array{
     *     id: string,
     *     user_id: string
     * }  $args
     */
    public function __invoke($_, array $args): Article
    {
        $tenant = tenant();

        if (!($tenant instanceof Tenant)) {
            throw new HttpException(ErrorCode::NOT_FOUND);
        }

        /** @var Article|null $article */
        $article = Article::find($args['id']);

        if (!($article instanceof Article)) {
            throw new NotFoundHttpException();
        }

        $this->authorize('write', $article);

        $article->authors()->detach($args['user_id']);

        $article->refresh();

        $article->searchable();

        // will be removed after SPMVP-6583
        DeskUserRemoved::dispatch($tenant->id, $article->desk_id, (int) $args['user_id']);

        UserActivity::log(
            name: 'article.authors.remove',
            subject: $article,
            data: [
                'user' => $args['user_id'],
            ],
        );

        return $article;
    }
}
