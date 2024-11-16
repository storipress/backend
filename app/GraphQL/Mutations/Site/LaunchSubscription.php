<?php

namespace App\GraphQL\Mutations\Site;

use App\Builder\ReleaseEventsBuilder;
use App\Enums\Subscription\Setup;
use App\Exceptions\BadRequestHttpException;
use App\GraphQL\Mutations\Mutation;
use App\Models\Tenant;
use App\Models\Tenants\UserActivity;
use Segment\Segment;
use Webmozart\Assert\Assert;

final class LaunchSubscription extends Mutation
{
    /**
     * @param  array{}  $args
     */
    public function __invoke($_, array $args): Tenant
    {
        $this->authorize('write', Tenant::class);

        $tenant = tenant();

        Assert::isInstanceOf($tenant, Tenant::class);

        $step = $tenant->subscription_setup;

        if (Setup::waitConnectStripe()->is($step)) {
            throw new BadRequestHttpException();
        }

        $tenant->update([
            'subscription_setup' => Setup::done(),
            'subscription_setup_done' => true,
        ]);

        UserActivity::log(
            name: 'member.launch',
        );

        Segment::track([
            'userId' => (string) auth()->id(),
            'event' => 'tenant_member_subscription_enabled',
            'properties' => [
                'tenant_uid' => $tenant->id,
                'tenant_name' => $tenant->name,
            ],
            'context' => [
                'groupId' => $tenant->id,
            ],
        ]);

        $builder = new ReleaseEventsBuilder();

        $builder->handle('subscription:launch');

        return $tenant;
    }
}
