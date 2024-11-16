<?php

namespace App\GraphQL\Mutations\Scraper;

use App\Exceptions\NotFoundHttpException;
use App\Models\Tenants\Scraper;
use App\Models\Tenants\ScraperArticle;
use App\Models\Tenants\UserActivity;

class DeleteScraperArticle extends ScraperMutation
{
    /**
     * @param  array{
     *     token: string,
     *     id: string,
     * }  $args
     */
    public function __invoke($_, array $args): ScraperArticle
    {
        /** @var Scraper|null $scraper */
        $scraper = Scraper::find(
            $this->parseJWT($args['token'])->get('sid'),
        );

        if ($scraper === null) {
            throw new NotFoundHttpException();
        }

        /** @var ScraperArticle|null $article */
        $article = $scraper->articles()
            ->where('scraper_articles.id', $args['id'])
            ->first();

        if ($article === null) {
            throw new NotFoundHttpException();
        }

        $article->delete();

        $scraper->update(['total' => $scraper->articles()->count()]);

        UserActivity::log(
            name: 'scraper.articles.delete',
            subject: $article,
        );

        return $article;
    }
}
