<?php

namespace App\GraphQL\Mutations\Article;

use App\Events\Entity\Desk\DeskUserAdded;
use App\Exceptions\ErrorCode;
use App\Exceptions\HttpException;
use App\Exceptions\NotFoundHttpException;
use App\Models\Tenant;
use App\Models\Tenants\Article;
use App\Models\Tenants\UserActivity;

final class AddAuthorToArticle extends ArticleMutation
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

        if (! ($tenant instanceof Tenant)) {
            throw new HttpException(ErrorCode::NOT_FOUND);
        }

        /** @var Article|null $article */
        $article = Article::find($args['id']);

        if (is_null($article)) {
            throw new NotFoundHttpException();
        }

        $this->authorize('write', $article);

        $result = $article->authors()->syncWithoutDetaching([$args['user_id']]);

        if (count($result['attached']) === 0) {
            return $article;
        }

        $article->refresh();

        $article->searchable();

        // will be removed after SPMVP-6583
        DeskUserAdded::dispatch($tenant->id, $article->desk_id, (int) $args['user_id']);

        UserActivity::log(
            name: 'article.authors.add',
            subject: $article,
            data: [
                'user' => $args['user_id'],
            ],
        );

        return $article;
    }
}
