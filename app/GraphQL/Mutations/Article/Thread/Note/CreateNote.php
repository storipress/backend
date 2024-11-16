<?php

namespace App\GraphQL\Mutations\Article\Thread\Note;

use App\Exceptions\NotFoundHttpException;
use App\GraphQL\Mutations\Article\ArticleMutation;
use App\Models\Tenants\ArticleThread;
use App\Models\Tenants\Note;
use App\Models\Tenants\User;
use App\Models\Tenants\UserActivity;

final class CreateNote extends ArticleMutation
{
    /**
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args): Note
    {
        /** @var ArticleThread|null $thread */
        $thread = ArticleThread::find($args['thread_id']);

        if ($thread === null) {
            throw new NotFoundHttpException();
        }

        $this->authorize('write', $thread->article);

        /** @var User $manipulator */
        $manipulator = User::find(auth()->user()?->getAuthIdentifier());

        /** @var Note $note */
        $note = $thread->notes()->create([
            'article_id' => $thread->article_id,
            'user_id' => $manipulator->getKey(),
            'content' => $args['content'],
        ]);

        UserActivity::log(
            name: 'article.threads.notes.create',
            subject: $note,
        );

        return $note;
    }
}
