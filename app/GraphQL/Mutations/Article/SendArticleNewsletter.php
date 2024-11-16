<?php

namespace App\GraphQL\Mutations\Article;

use App\Exceptions\NotFoundHttpException;
use App\Jobs\Subscriber\SendArticleNewsletter as SendArticleNewsletterJob;
use App\Models\Tenants\Article;
use App\Models\Tenants\UserActivity;

class SendArticleNewsletter extends ArticleMutation
{
    /**
     * @param  array{id: string}  $args
     */
    public function __invoke($_, array $args): Article
    {
        $tenantId = tenant('id');

        if (!is_string($tenantId)) {
            throw new NotFoundHttpException();
        }

        /** @var Article|null $article */
        $article = Article::find($args['id']);

        if ($article === null) {
            throw new NotFoundHttpException();
        }

        if ($article->newsletter_at !== null) {
            return $article;
        }

        $this->authorize('write', $article);

        SendArticleNewsletterJob::dispatch(
            tenantId: $tenantId,
            articleId: $article->id,
        );

        UserActivity::log(
            name: 'article.newsletter.send',
            subject: $article,
        );

        return $article;
    }
}
