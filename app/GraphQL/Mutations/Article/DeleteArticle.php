<?php

namespace App\GraphQL\Mutations\Article;

use App\Builder\ReleaseEventsBuilder;
use App\Events\Entity\Article\ArticleDeleted;
use App\Exceptions\InternalServerErrorHttpException;
use App\Exceptions\NotFoundHttpException;
use App\Models\Tenant;
use App\Models\Tenants\Article;
use App\Models\Tenants\UserActivity;
use Exception;

final class DeleteArticle extends ArticleMutation
{
    /**
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args): Article
    {
        $tenant = tenant();

        if (!($tenant instanceof Tenant)) {
            throw new NotFoundHttpException();
        }

        /** @var Article|null $article */
        $article = Article::find($args['id']);

        if (is_null($article)) {
            throw new NotFoundHttpException();
        }

        $this->authorize('write', $article);

        try {
            $deleted = $article->delete();
        } catch (Exception $e) {
            throw new InternalServerErrorHttpException();
        }

        if (!$deleted) {
            throw new InternalServerErrorHttpException();
        }

        ArticleDeleted::dispatch(
            $tenant->id,
            $article->id,
        );

        UserActivity::log(
            name: 'article.delete',
            subject: $article,
        );

        $builder = new ReleaseEventsBuilder();

        $builder->handle('article:delete', ['id' => $article->getKey()]);

        return $article;
    }
}
