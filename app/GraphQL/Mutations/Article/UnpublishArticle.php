<?php

namespace App\GraphQL\Mutations\Article;

use App\Enums\Article\PublishType;
use App\Events\Entity\Article\ArticleUnpublished;
use App\Exceptions\InternalServerErrorHttpException;
use App\Exceptions\NotFoundHttpException;
use App\Models\Tenant;
use App\Models\Tenants\Article;
use App\Models\Tenants\UserActivity;
use Webmozart\Assert\Assert;

final class UnpublishArticle extends ArticleMutation
{
    /**
     * @param  array<string, string>  $args
     */
    public function __invoke($_, array $args): Article
    {
        $tenant = tenant();

        Assert::isInstanceOf($tenant, Tenant::class);

        $article = Article::find($args['id']);

        if (is_null($article)) {
            throw new NotFoundHttpException();
        }

        $this->authorize('write', $article);

        $updated = $article->update([
            'published_at' => null,
            'publish_type' => PublishType::none(),
        ]);

        if (! $updated) {
            throw new InternalServerErrorHttpException();
        }

        ArticleUnpublished::dispatch($tenant->id, $article->id);

        UserActivity::log(
            name: 'article.unschedule',
            subject: $article,
        );

        return $article;
    }
}
