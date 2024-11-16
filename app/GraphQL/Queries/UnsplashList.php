<?php

namespace App\GraphQL\Queries;

use Illuminate\Support\Facades\Cache;
use Unsplash\ArrayObject;

final class UnsplashList
{
    /**
     * @param  array{
     *     page: int,
     * }  $args
     * @return mixed[]
     */
    public function __invoke($_, array $args): array
    {
        $page = max($args['page'], 1);

        $key = sprintf('unsplash-list-page-%d', $page);

        /** @var ArrayObject<mixed> $result */
        $result = Cache::remember(
            $key,
            now()->addMinutes(15),
            fn () => app('unsplash')->list($page),
        );

        return $result->toArray();
    }
}
