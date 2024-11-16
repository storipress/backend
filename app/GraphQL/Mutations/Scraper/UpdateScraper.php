<?php

namespace App\GraphQL\Mutations\Scraper;

use App\Enums\Scraper\State;
use App\Exceptions\NotFoundHttpException;
use App\Models\Tenants\Scraper;
use App\Models\Tenants\UserActivity;
use Illuminate\Support\Arr;

class UpdateScraper extends ScraperMutation
{
    /**
     * @param  array{
     *     token: string,
     *     state?: State,
     *     data?: mixed[]|null,
     *     finished_at?: string,
     *     failed_at?: string,
     * }  $args
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

        $attributes = Arr::except($args, ['token']);

        $origin = $scraper->only(array_keys($attributes));

        $scraper->update($attributes);

        UserActivity::log(
            name: 'scraper.update',
            subject: $scraper,
            data: [
                'old' => $origin,
                'new' => $attributes,
            ],
        );

        return $scraper;
    }
}
