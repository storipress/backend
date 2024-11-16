<?php

namespace App\GraphQL\Mutations\Article\Thread;

use App\Exceptions\NotFoundHttpException;
use App\GraphQL\Mutations\Article\ArticleMutation;
use App\Models\Tenants\Article;
use App\Models\Tenants\ArticleThread;
use App\Models\Tenants\UserActivity;
use Illuminate\Support\Arr;

final class CreateArticleThread extends ArticleMutation
{
    /**
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args): ArticleThread
    {
        /** @var Article|null $article */
        $article = Article::find($args['article_id']);

        if (is_null($article)) {
            throw new NotFoundHttpException();
        }

        $this->authorize('write', $article);

        /** @var ArticleThread $thread */
        $thread = $article->threads()->create(
            Arr::except($args, ['article_id']),
        );

        UserActivity::log(
            name: 'article.threads.create',
            subject: $thread,
        );

        return $thread;
    }
}
