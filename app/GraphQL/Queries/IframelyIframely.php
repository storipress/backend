<?php

namespace App\GraphQL\Queries;

use App\Exceptions\BadRequestHttpException;
use App\GraphQL\Traits\DomainHelper;
use Illuminate\Support\Facades\Cache;

final class IframelyIframely
{
    use DomainHelper;

    /**
     * @param  array<string, mixed>  $args
     * @return mixed[]
     */
    public function __invoke($_, array $args): array
    {
        $encoded = json_encode($args['params']);

        if (! $encoded) {
            throw new BadRequestHttpException();
        }

        /** @var array<string, int|string> $params */
        $params = json_decode($encoded, true);

        ksort($params);

        /** @var string $url */
        $url = $args['url'];

        /** @var string $host */
        $host = parse_url($url, PHP_URL_HOST);

        if ($this->isMaliciousHostname($host) || $this->isStoripressDomain($host)) {
            throw new BadRequestHttpException();
        }

        $key = sprintf('iframely-iframely-%s-%s', md5($url), md5(serialize($params)));

        /** @var array<string, mixed> $data */
        $data = tenancy()->central(fn () => Cache::remember(
            $key,
            now()->addMinutes(mt_rand(
                60 * 24 * 7, // 7 days in minutes
                60 * 24 * 30, // 30 days in minutes
            )),
            fn () => app('iframely')->iframely($url, $params),
        ));

        return $data;
    }
}
