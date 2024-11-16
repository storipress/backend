<?php

namespace App\GraphQL\Mutations\Article;

use App\Events\Entity\Article\ArticleUnpublished;
use App\Exceptions\InternalServerErrorHttpException;
use App\Exceptions\NotFoundHttpException;
use App\Jobs\Webflow\SyncArticleToWebflow;
use App\Models\Tenants\Article;
use App\Models\Tenants\UserActivity;
use App\Models\User;

class ChangeArticleStage extends ArticleMutation
{
    /**
     * @param  array<string, string>  $args
     */
    public function __invoke($_, array $args): Article
    {
        $tenant = tenant_or_fail();

        $article = Article::with('authors')->find($args['id']);

        if (! ($article instanceof Article)) {
            throw new NotFoundHttpException();
        }

        $user = auth()->user();

        if (! ($user instanceof User)) {
            throw new NotFoundHttpException();
        }

        if ($article->authors->where('id', $user->id)->isEmpty()) {
            $this->authorize('write', $article);
        }

        $published = $article->published;

        $originStageId = $article->stage_id;

        if ($originStageId === (int) $args['stage_id']) {
            return $article;
        }

        $order = Article::whereStageId($args['stage_id'])->max('order') ?: 0;

        $updated = $article->update([
            'stage_id' => $args['stage_id'],
            'order' => $order + 1,
        ]);

        if (! $updated) {
            throw new InternalServerErrorHttpException();
        }

        $article->refresh();

        if ($published && ! $article->published) {
            ArticleUnpublished::dispatch($tenant->id, $article->id);
        }

        if ($article->stage->ready) {
            SyncArticleToWebflow::dispatch(
                $tenant->id,
                $article->id,
            );
        }

        UserActivity::log(
            name: 'article.stage.change',
            subject: $article,
            data: [
                'old' => $originStageId,
                'new' => (int) $args['stage_id'],
            ],
        );

        return $article;
    }
}
