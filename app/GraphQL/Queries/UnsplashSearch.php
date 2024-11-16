<?php

namespace App\GraphQL\Queries;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Unsplash\PageResult;

final class UnsplashSearch
{
    /**
     * @param  array<string, mixed>  $args
     * @return mixed[]
     */
    public function __invoke($_, array $args): array
    {
        $params = Arr::only(
            $args,
            ['keyword', 'page', 'orientation'],
        );

        $hash = md5(implode('|', $params));

        $key = sprintf('unsplash-search-%s', $hash);

        /** @var PageResult $result */
        $result = Cache::remember(
            $key,
            now()->addHour(),
            fn () => app('unsplash')->search(...$params),
        );

        return $result->getResults();
    }
}
