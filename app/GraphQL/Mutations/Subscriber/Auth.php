<?php

namespace App\GraphQL\Mutations\Subscriber;

use App\Models\Tenant;
use App\Tools\DomainParser;
use Spatie\Url\Url;

trait Auth
{
    /**
     * Transform referer to human-readable source.
     */
    protected function guessSource(string $referer): string
    {
        return DomainParser::toBrandName($referer);
    }

    /**
     * Generate sign in link.
     */
    protected function link(string $from, string $token, string $action): string
    {
        $url = $this->fromToUrl($from)
            ->withQueryParameter('token', encrypt($token))
            ->withQueryParameter('action', $action);

        return rawurldecode((string) $url);
    }

    /**
     * Validate and reorganize from link.
     */
    protected function fromToUrl(string $from): Url
    {
        $url = Url::fromString($from);

        /** @var Tenant $tenant */
        $tenant = tenant();

        $host = $tenant->url;

        if ($url->getHost() !== $host) {
            $url = $url->withHost($host)
                ->withPath('/')
                ->withQuery('');
        }

        return $url->withScheme('https');
    }
}
