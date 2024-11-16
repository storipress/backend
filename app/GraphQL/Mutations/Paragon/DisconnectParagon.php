<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\Paragon;

use App\Console\Schedules\Daily\SendColdEmailToSubscribers;

final readonly class DisconnectParagon
{
    /**
     * @param  array{}  $args
     */
    public function __invoke(null $_, array $args): bool
    {
        $api = new SendColdEmailToSubscribers();

        $id = $api->getConnectionId(tenant_or_fail()->id);

        if ($id === null) {
            return false;
        }

        $url = sprintf('https://api.integration.app/connections/%s', $id);

        return app('http2')
            ->withToken($api->jwt('N/A', true))
            ->delete($url)
            ->successful();
    }
}
