<?php

namespace App\GraphQL\Mutations\Article\Thread\Note;

use App\Exceptions\InternalServerErrorHttpException;
use App\Exceptions\NotFoundHttpException;
use App\GraphQL\Mutations\Article\ArticleMutation;
use App\Models\Tenants\Note;
use App\Models\Tenants\UserActivity;
use Illuminate\Support\Arr;

final class UpdateNote extends ArticleMutation
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

        $this->authorize('write', $note->article);

        $attributes = Arr::except($args, ['id']);

        $origin = $note->only(array_keys($attributes));

        $updated = $note->update($attributes);

        if (!$updated) {
            throw new InternalServerErrorHttpException();
        }

        UserActivity::log(
            name: 'article.threads.notes.update',
            subject: $note,
            data: [
                'old' => $origin,
                'new' => $attributes,
            ],
        );

        return $note;
    }
}
