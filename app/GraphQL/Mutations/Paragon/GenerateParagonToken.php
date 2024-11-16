<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\Paragon;

use App\Console\Schedules\Daily\SendColdEmailToSubscribers;

final readonly class GenerateParagonToken
{
    /** @param  array{}  $args */
    public function __invoke(null $_, array $args): string
    {
        return (new SendColdEmailToSubscribers())->jwt(
            tenant_or_fail()->id,
        );
    }
}
