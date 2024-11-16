<?php

namespace App\GraphQL\Mutations\Article;

use App\Exceptions\NotFoundHttpException;
use App\Models\Tenants\Article;
use App\Models\Tenants\UserActivity;

final class AddTagToArticle extends ArticleMutation
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

        $article->tags()->syncWithoutDetaching([$args['tag_id']]);

        $article->refresh();

        $article->searchable();

        UserActivity::log(
            name: 'article.tags.add',
            subject: $article,
            data: [
                'tag' => $args['tag_id'],
            ],
        );

        return $article;
    }
}
