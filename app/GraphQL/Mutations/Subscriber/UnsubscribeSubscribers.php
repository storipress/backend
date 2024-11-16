<?php

namespace App\GraphQL\Mutations\Subscriber;

use App\Models\Tenants\Subscriber;

class UnsubscribeSubscribers
{
    /**
     * @param  array{
     *   ids: string[],
     * }  $args
     */
    public function __invoke($_, array $args): bool
    {
        Subscriber::whereIn('id', $args['ids'])->update([
            'newsletter' => false,
        ]);

        return true;
    }
}
