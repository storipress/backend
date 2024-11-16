<?php

namespace App\GraphQL\Mutations\Article;

use App\Builder\ReleaseEventsBuilder;
use App\Events\Entity\Article\ArticleRestored;
use App\Exceptions\InternalServerErrorHttpException;
use App\Exceptions\NotFoundHttpException;
use App\Models\Tenant;
use App\Models\Tenants\Article;
use App\Models\Tenants\UserActivity;
use Illuminate\Support\Str;
use Webmozart\Assert\Assert;

class RestoreArticle extends ArticleMutation
{
    /**
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args): Article
    {
        $tenant = tenant();

        Assert::isInstanceOf($tenant, Tenant::class);

        /** @var Article|null $article */
        $article = Article::onlyTrashed()->find($args['id']);

        if (is_null($article)) {
            throw new NotFoundHttpException();
        }

        $this->authorize('write', $article);

        $order = Article::whereStageId($article->stage_id)->max('order') ?: 0;

        $article->setAttribute('order', $order + 1);

        if (preg_match('/-\d{10}$/i', $article->slug) === 1) {
            $article->slug = Str::beforeLast($article->slug, '-');
        }

        if (!$article->restore()) {
            throw new InternalServerErrorHttpException();
        }

        ArticleRestored::dispatch($tenant->id, $article->id);

        UserActivity::log(
            name: 'article.restore',
            subject: $article,
        );

        $builder = new ReleaseEventsBuilder();

        $builder->handle('article:restore', ['id' => $article->getKey()]);

        return $article;
    }
}
