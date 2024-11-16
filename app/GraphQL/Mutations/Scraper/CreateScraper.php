<?php

namespace App\GraphQL\Mutations\Scraper;

use App\Models\Tenants\Scraper;
use App\Models\Tenants\UserActivity;

class CreateScraper extends ScraperMutation
{
    /**
     * @param  array{}  $args
     */
    public function __invoke($_, array $args): string
    {
        $scraper = Scraper::create();

        UserActivity::log(
            name: 'scraper.create',
            subject: $scraper,
        );

        return $this->issueJWT($scraper->id);
    }
}
