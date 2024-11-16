<?php

namespace App\GraphQL\Mutations\Site;

use App\Builder\ReleaseEventsBuilder;
use App\GraphQL\Mutations\Mutation;
use App\Models\Tenant;
use Segment\Segment;

class DisableSubscription extends Mutation
{
    /**
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args): Tenant
    {
        $this->authorize('write', Tenant::class);

        /** @var Tenant $tenant */
        $tenant = tenant();

        $tenant->update([
            'subscription' => false,
        ]);

        Segment::track([
            'userId' => (string) auth()->id(),
            'event' => 'tenant_member_subscription_disabled',
            'properties' => [
                'tenant_uid' => $tenant->id,
                'tenant_name' => $tenant->name,
            ],
            'context' => [
                'groupId' => $tenant->id,
            ],
        ]);

        $builder = new ReleaseEventsBuilder();

        $builder->handle('subscription:disable');

        return $tenant;
    }
}
