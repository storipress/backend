<?php

declare(strict_types=1);

namespace App\GraphQL\Queries\Facebook;

use Illuminate\Support\Str;
use Storipress\Facebook\Exceptions\FacebookException;
use Storipress\Facebook\Objects\SearchPage;

use function Sentry\captureException;

final readonly class FacebookPages
{
    /**
     * @param  array{
     *     keyword: string,
     * }  $args
     * @return array<int, SearchPage>
     */
    public function __invoke(null $_, array $args): array
    {
        $keyword = trim($args['keyword']);

        if (empty($keyword)) {
            return [];
        }

        try {
            $pages = app('facebook')->page()->search($keyword);
        } catch (FacebookException $e) {
            if (!Str::contains($e->getMessage(), 'OAuthException')) {
                captureException($e);
            }

            return [];
        }

        return $pages;
    }
}
