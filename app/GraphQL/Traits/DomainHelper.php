<?php

namespace App\GraphQL\Traits;

use Illuminate\Support\Str;

trait DomainHelper
{
    /**
     * Check if host is localhost.
     */
    protected function isLocalhost(string $host): bool
    {
        return $host === 'localhost';
    }

    /**
     * Check if host is storipress domain.
     */
    protected function isStoripressDomain(string $host): bool
    {
        $storipressDomains = [
            'stori.press',
            'storipress.pro',
            'storipress.dev',
        ];

        return Str::endsWith($host, $storipressDomains);
    }

    /**
     * Check if host is private ip address.
     */
    protected function isPrivateIpAddress(string $host): bool
    {
        if (!filter_var($host, FILTER_VALIDATE_IP)) {
            return false;
        }

        return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }

    /**
     * Check if host is malicious host name.
     */
    protected function isMaliciousHostname(string $host): bool
    {
        return $this->isLocalhost($host) || $this->isPrivateIpAddress($host);
    }
}
