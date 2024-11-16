<?php

namespace App\GraphQL\Mutations\Subscriber;

use App\Models\Tenant;
use App\Models\Tenants\Subscriber;

class DeleteSubscribers
{
    /**
     * @param  array<string, array<int, string>>  $args
     */
    public function __invoke($_, array $args): bool
    {
        $ids = $args['ids'];

        foreach ($ids as $id) {
            /** @var Subscriber|null $subscriber */
            $subscriber = Subscriber::find($id);

            if (is_null($subscriber)) {
                continue;
            }

            // @todo handle pro-rated refund

            $subscriber->delete();
        }

        /** @var Tenant $tenant */
        $tenant = tenant();

        $tenant->subscribers()->detach($ids);

        return true;
    }
}
