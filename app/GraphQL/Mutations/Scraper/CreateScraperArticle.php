<?php

namespace App\GraphQL\Mutations\Scraper;

use App\Exceptions\NotFoundHttpException;
use App\Models\Tenants\Scraper;
use App\Models\Tenants\ScraperArticle;
use App\Models\Tenants\UserActivity;

class CreateScraperArticle extends ScraperMutation
{
    /**
     * @param  array{
     *     token: string,
     *     path: string[],
     * }  $args
     * @return ScraperArticle[]
     */
    public function __invoke($_, array $args): array
    {
        /** @var Scraper|null $scraper */
        $scraper = Scraper::find(
            $this->parseJWT($args['token'])->get('sid'),
        );

        if ($scraper === null) {
            throw new NotFoundHttpException();
        }

        $articles = [];

        foreach ($args['path'] as $path) {
            $articles[] = $article = $scraper->articles()->create([
                'path' => $path,
            ]);

            UserActivity::log(
                name: 'scraper.articles.create',
                subject: $article,
            );
        }

        $scraper->update(['total' => $scraper->articles()->count()]);

        return $articles;
    }
}
