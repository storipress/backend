<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\Subscriber;

final readonly class SendColdEmailToSubscriber
{
    /**
     * @param  array{
     *     id: string,
     *     subject: string,
     *     content: string,
     *     reply_to?: string,
     * }  $args
     */
    public function __invoke(null $_, array $args): bool
    {
        if (app()->runningUnitTests()) {
            return false;
        }

        return true;
    }
}
