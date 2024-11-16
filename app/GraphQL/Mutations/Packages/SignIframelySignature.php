<?php

namespace App\GraphQL\Mutations\Packages;

use App\Exceptions\BadRequestHttpException;
use App\GraphQL\Traits\DomainHelper;
use App\Models\Tenants\UserActivity;

class SignIframelySignature
{
    use DomainHelper;

    /**
     * @param  array{
     *     params: array<string, string>,
     * }  $args
     */
    public function __invoke($_, array $args): string
    {
        $encoded = json_encode($args['params'], JSON_UNESCAPED_SLASHES);

        if (!$encoded) {
            throw new BadRequestHttpException();
        }

        /** @var array<string, string> $params */
        $params = json_decode($encoded, true);

        ksort($params);

        $host = parse_url($params['url'] ?? '', PHP_URL_HOST);

        if (!is_string($host)) {
            throw new BadRequestHttpException();
        }

        if ($this->isMaliciousHostname($host) || $this->isStoripressDomain($host)) {
            throw new BadRequestHttpException();
        }

        UserActivity::log(
            name: 'iframely.sign',
            data: $params,
        );

        /** @var string $payload */
        $payload = json_encode($params, JSON_UNESCAPED_SLASHES);

        return hash_hmac(
            'sha256',
            $payload,
            'jJVYmScSW38zR08gZwNkOHOaHMRzFinU',
        );
    }
}
