<?php

namespace App\GraphQL\Queries\Scraper;

use App\Exceptions\NotFoundHttpException;
use App\GraphQL\Traits\ScraperHelper;
use App\Models\Tenants\Article;
use App\Models\Tenants\Scraper as ScraperModel;

class ScraperPendingInviteUsers
{
    use ScraperHelper;

    /**
     * @param  array{
     *     token: string,
     * }  $args
     * @return string[]
     */
    public function __invoke($_, array $args): array
    {
        /** @var ScraperModel|null $scraper */
        $scraper = ScraperModel::find(
            $this->parseJWT($args['token'])->get('sid'),
        );

        if ($scraper === null) {
            throw new NotFoundHttpException();
        }

        $ids = $scraper->articles()
            ->whereNotNull('article_id')
            ->where('successful', true)
            ->pluck('article_id')
            ->toArray();

        /** @var string[] $names */
        $names = Article::whereNotNull('shadow_authors')
            ->whereIn('id', $ids)
            ->pluck('shadow_authors')
            ->flatten()
            ->unique()
            ->sort()
            ->toArray();

        return $names;
    }
}
