<?php

namespace App\GraphQL\Mutations\Scraper;

use App\Exceptions\NotFoundHttpException;
use App\Models\Tenants\Scraper;
use App\Models\Tenants\ScraperArticle;
use App\Models\Tenants\UserActivity;
use Illuminate\Support\Arr;

class UpdateScraperArticle extends ScraperMutation
{
    /**
     * @param  array{
     *     token: string,
     *     id: string,
     *     data?: mixed[]|null,
     *     successful?: bool,
     *     scraped_at?: string,
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

        $attributes = Arr::except($args, ['token', 'id']);

        $origin = $article->only(array_keys($attributes));

        $article->update($attributes);

        if ($article->wasChanged(['successful', 'scraped_at'])) {
            $query = $scraper->articles()->whereNotNull('scraped_at');

            $scraper->update([
                'successful' => $query->clone()->where('successful', true)->count(),
                'failed' => $query->clone()->where('successful', false)->count(),
            ]);
        }

        UserActivity::log(
            name: 'scraper.articles.update',
            subject: $article,
            data: [
                'old' => $origin,
                'new' => $attributes,
            ],
        );

        return $article;
    }
}
