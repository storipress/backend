<?php

namespace App\GraphQL\Mutations\Site;

use App\Enums\Subscription\Setup;
use App\Exceptions\BadRequestHttpException;
use App\Models\Tenant;
use App\Models\Tenants\UserActivity;
use Laravel\Cashier\Cashier;
use Segment\Segment;
use Stripe\Exception\ApiErrorException;
use Webmozart\Assert\Assert;

class CheckStripeConnectConnected
{
    use StripeTrait;

    /**
     * @param  array<string, mixed>  $args
     *
     * @throws ApiErrorException
     */
    public function __invoke($_, array $args): bool
    {
        $tenant = tenant();

        Assert::isInstanceOf($tenant, Tenant::class);

        $setup = $tenant->subscription_setup;

        if (Setup::done()->is($setup)) {
            return true;
        }

        if (Setup::waitConnectStripe()->isNot($setup)) {
            throw new BadRequestHttpException();
        }

        $id = $tenant->stripe_account_id;

        Assert::nullOrString($id);

        if (empty($id)) {
            return false;
        }

        $stripe = Cashier::stripe();

        $account = $stripe->accounts->retrieve($id);

        $pass = $account->details_submitted;

        if (! $pass) {
            return false;
        }

        $tenant->update([
            'subscription_setup' => Setup::waitImport(),
        ]);

        $this->updateStripeProduct();

        UserActivity::log(
            name: 'member.stripe.connect',
        );

        Segment::track([
            'userId' => (string) auth()->id(),
            'event' => 'tenant_member_stripe_connected',
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
