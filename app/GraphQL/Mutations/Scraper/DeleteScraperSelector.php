<?php

namespace App\GraphQL\Mutations\Scraper;

use App\Exceptions\NotFoundHttpException;
use App\Models\Tenants\Scraper;
use App\Models\Tenants\ScraperSelector;
use App\Models\Tenants\UserActivity;

class DeleteScraperSelector extends ScraperMutation
{
    /**
     * @param  array{
     *     token: string,
     *     id: string,
     * }  $args
     */
    public function __invoke($_, array $args): ScraperSelector
    {
        /** @var Scraper|null $scraper */
        $scraper = Scraper::find(
            $this->parseJWT($args['token'])->get('sid'),
        );

        if ($scraper === null) {
            throw new NotFoundHttpException();
        }

        /** @var ScraperSelector|null $selector */
        $selector = $scraper->selectors()
            ->where('scraper_selectors.id', $args['id'])
            ->first();

        if ($selector === null) {
            throw new NotFoundHttpException();
        }

        $selector->delete();

        UserActivity::log(
            name: 'scraper.selectors.delete',
            subject: $selector,
        );

        return $selector;
    }
}
