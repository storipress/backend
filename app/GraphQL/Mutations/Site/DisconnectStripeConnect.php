<?php

namespace App\GraphQL\Mutations\Site;

use App\Enums\Subscription\Setup;
use App\Models\Tenant;
use App\Models\Tenants\UserActivity;
use Laravel\Cashier\Cashier;
use Segment\Segment;
use Webmozart\Assert\Assert;

class DisconnectStripeConnect
{
    /**
     * @param  array<string, mixed>  $args
     *
     * @throws \Stripe\Exception\ApiErrorException
     */
    public function __invoke($_, array $args): bool
    {
        $tenant = tenant();

        Assert::isInstanceOf($tenant, Tenant::class);

        $id = $tenant->stripe_account_id;

        Assert::nullOrString($id);

        if (empty($id)) {
            return true;
        }

        $stripe = Cashier::stripe();

        $account = $stripe->accounts->delete($id);

        if (! $account->isDeleted()) {
            return false;
        }

        $tenant->update([
            'subscription_setup' => Setup::waitConnectStripe(),
            'stripe_account_id' => null,
            'stripe_product_id' => null,
            'stripe_monthly_price_id' => null,
            'stripe_yearly_price_id' => null,
        ]);

        UserActivity::log(
            name: 'member.stripe.disconnect',
        );

        Segment::track([
            'userId' => (string) auth()->id(),
            'event' => 'tenant_member_stripe_disconnected',
            'properties' => [
                'tenant_uid' => $tenant->id,
                'tenant_name' => $tenant->name,
            ],
            'context' => [
                'groupId' => $tenant->id,
            ],
        ]);

        return true;
    }
}
