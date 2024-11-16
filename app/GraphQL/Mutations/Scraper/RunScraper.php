<?php

namespace App\GraphQL\Mutations\Scraper;

use App\Enums\Scraper\Type;
use App\Exceptions\NotFoundHttpException;
use App\Jobs\Scraper\StartScraperRunner;
use App\Models\Tenants\Scraper;
use App\Models\Tenants\UserActivity;
use Exception;

class RunScraper extends ScraperMutation
{
    /**
     * @param  array{
     *     token: string,
     *     type: Type,
     * }  $args
     *
     * @throws Exception
     */
    public function __invoke($_, array $args): Scraper
    {
        /** @var Scraper|null $scraper */
        $scraper = Scraper::find(
            $this->parseJWT($args['token'])->get('sid'),
        );

        if ($scraper === null) {
            throw new NotFoundHttpException();
        }

        StartScraperRunner::dispatch(
            id: $scraper->id,
            token: $args['token'],
            type: $args['type']->value,
            tenant: tenant('id'), // @phpstan-ignore-line
        );

        UserActivity::log(
            name: 'scraper.run',
            subject: $scraper,
        );

        return $scraper;
    }
}
