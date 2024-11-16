<?php

namespace App\GraphQL\Mutations\Subscriber;

use App\Models\Tenants\Subscriber;

class SubscribeSubscribers
{
    /**
     * @param  array{
     *   ids: string[],
     * }  $args
     */
    public function __invoke($_, array $args): bool
    {
        Subscriber::whereIn('id', $args['ids'])->update([
            'newsletter' => true,
        ]);

        return true;
    }
}
