<?php

namespace App\GraphQL\Mutations\Scraper;

use App\Exceptions\NotFoundHttpException;
use App\Models\Tenants\Scraper;
use App\Models\Tenants\ScraperSelector;
use App\Models\Tenants\UserActivity;
use Illuminate\Support\Arr;

class CreateScraperSelector extends ScraperMutation
{
    /**
     * @param  array{
     *     token: string,
     *     type: string,
     *     value?: string|null,
     *     data?: mixed[]|null,
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

        /** @var ScraperSelector $selector */
        $selector = $scraper->selectors()->create(
            Arr::except($args, ['token']),
        );

        UserActivity::log(
            name: 'scraper.selectors.create',
            subject: $selector,
        );

        return $selector;
    }
}
