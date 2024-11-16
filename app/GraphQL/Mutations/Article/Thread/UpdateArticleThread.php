<?php

namespace App\GraphQL\Mutations\Article\Thread;

use App\Exceptions\InternalServerErrorHttpException;
use App\Exceptions\NotFoundHttpException;
use App\GraphQL\Mutations\Article\ArticleMutation;
use App\Models\Tenants\ArticleThread;
use App\Models\Tenants\UserActivity;
use Illuminate\Support\Arr;

final class UpdateArticleThread extends ArticleMutation
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

        $attributes = Arr::except($args, ['id']);

        $origin = $thread->only(array_keys($attributes));

        $updated = $thread->update($attributes);

        if (!$updated) {
            throw new InternalServerErrorHttpException();
        }

        UserActivity::log(
            name: 'article.threads.update',
            subject: $thread,
            data: [
                'old' => $origin,
                'new' => $attributes,
            ],
        );

        return $thread;
    }
}
