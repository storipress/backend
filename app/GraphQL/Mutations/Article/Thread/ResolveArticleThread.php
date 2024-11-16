<?php

namespace App\GraphQL\Mutations\Article\Thread;

use App\Exceptions\InternalServerErrorHttpException;
use App\Exceptions\NotFoundHttpException;
use App\GraphQL\Mutations\Article\ArticleMutation;
use App\Models\Tenants\ArticleThread;
use App\Models\Tenants\UserActivity;
use Exception;

final class ResolveArticleThread extends ArticleMutation
{
    /**
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args): ArticleThread
    {
        /** @var ArticleThread|null $thread */
        $thread = ArticleThread::find($args['id']);

        if ($thread === null) {
            throw new NotFoundHttpException();
        }

        $this->authorize('write', $thread->article);

        try {
            $deleted = $thread->delete();
        } catch (Exception $e) {
            throw new InternalServerErrorHttpException();
        }

        if (! $deleted) {
            throw new InternalServerErrorHttpException();
        }

        UserActivity::log(
            name: 'article.threads.resolve',
            subject: $thread,
        );

        return $thread;
    }
}
