<?php

namespace App\GraphQL\Mutations\Scraper;

use App\Exceptions\NotFoundHttpException;
use App\Jobs\Scraper\DownloadScrapedArticlesImages;
use App\Jobs\Scraper\ImportScrapedArticles;
use App\Jobs\Scraper\SendScraperResultEmail;
use App\Models\Tenants\Scraper;
use App\Models\Tenants\UserActivity;

class StartScraperTransfer extends ScraperMutation
{
    /**
     * @param  array{
     *     token: string,
     * }  $args
     */
    public function __invoke($_, array $args): bool
    {
        $jwt = $this->parseJWT($args['token']);

        /** @var Scraper|null $scraper */
        $scraper = Scraper::find($jwt->get('sid'));

        $tenantId = $jwt->get('cid');

        if ($scraper === null || !is_string($tenantId)) {
            throw new NotFoundHttpException();
        }

        ImportScrapedArticles::withChain([
            new DownloadScrapedArticlesImages($tenantId, $scraper->id),
            new SendScraperResultEmail($tenantId, $scraper->id, $args['token']),
        ])->dispatch($tenantId, $scraper->id);

        UserActivity::log(
            name: 'scraper.transfer',
            subject: $scraper,
        );

        return true;
    }
}
