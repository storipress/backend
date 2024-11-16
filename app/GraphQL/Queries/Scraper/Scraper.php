<?php

namespace App\GraphQL\Queries\Scraper;

use App\Exceptions\NotFoundHttpException;
use App\GraphQL\Traits\ScraperHelper;
use App\Models\Tenants\Scraper as ScraperModel;

class Scraper
{
    use ScraperHelper;

    /**
     * @param  array{
     *     token: string,
     *     id: string,
     * }  $args
     *
     * @retrun ScraperModel
     */
    public function __invoke($_, array $args): ScraperModel
    {
        try {
            $id = $this->parseJWT($args['token'])->get('sid');
        } catch (NotFoundHttpException) {
            $id = $args['token'];
        }

        $scraper = ScraperModel::find($id);

        if (!($scraper instanceof ScraperModel)) {
            throw new NotFoundHttpException();
        }

        return $scraper;
    }
}
