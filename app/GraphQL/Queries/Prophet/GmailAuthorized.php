<?php

declare(strict_types=1);

namespace App\GraphQL\Queries\Prophet;

use App\Console\Schedules\Daily\SendColdEmailToSubscribers;
use App\Models\Tenant;

final readonly class GmailAuthorized
{
    /**
     * @param  array{}  $args
     */
    public function __invoke(null $_, array $args): bool
    {
        $tenant = tenant();

        if (!($tenant instanceof Tenant)) {
            return false;
        }

        return (new SendColdEmailToSubscribers())->isConnectedToGmail($tenant->id);
    }
}
