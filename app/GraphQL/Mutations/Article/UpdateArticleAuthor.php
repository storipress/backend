<?php

namespace App\GraphQL\Mutations\Article;

use App\Exceptions\BadRequestHttpException;
use App\Exceptions\NotFoundHttpException;
use App\Models\Tenants\Article;

final class UpdateArticleAuthor extends ArticleMutation
{
    /**
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args): Article
    {
        /** @var Article|null $article */
        $article = Article::find($args['id']);

        if (is_null($article)) {
            throw new NotFoundHttpException();
        }

        $this->authorize('write', $article);

        $author = $article->authors()
            ->wherePivot('user_id', '=', $args['user_id'])
            ->first();

        if ($author === null) {
            throw new BadRequestHttpException();
        }

        return $article;
    }
}
