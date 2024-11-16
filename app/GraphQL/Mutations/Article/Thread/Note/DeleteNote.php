<?php

namespace App\GraphQL\Mutations\Article\Thread\Note;

use App\Exceptions\InternalServerErrorHttpException;
use App\Exceptions\NotFoundHttpException;
use App\GraphQL\Mutations\Article\ArticleMutation;
use App\Models\Tenants\Note;
use App\Models\Tenants\UserActivity;
use Exception;

final class DeleteNote extends ArticleMutation
{
    /**
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args): Note
    {
        /** @var Note|null $note */
        $note = Note::find($args['id']);

        if ($note === null) {
            throw new NotFoundHttpException();
        }

        // @phpstan-ignore-next-line
        if (! $note->article || ! $note->thread) {
            throw new NotFoundHttpException();
        }

        $this->authorize('write', $note->article);

        try {
            $deleted = $note->delete();
        } catch (Exception $e) {
            throw new InternalServerErrorHttpException();
        }

        if (! $deleted) {
            throw new InternalServerErrorHttpException();
        }

        UserActivity::log(
            name: 'article.threads.notes.delete',
            subject: $note,
        );

        return $note;
    }
}
