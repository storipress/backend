<?php

namespace App\GraphQL\Mutations\Site;

use App\Enums\Subscription\Setup;
use App\Exceptions\BadRequestHttpException;
use App\Models\Tenant;
use App\Models\Tenants\UserActivity;
use Laravel\Cashier\Cashier;
use Segment\Segment;
use Stripe\StripeClient;
use Webmozart\Assert\Assert;

class RequestStripeConnect
{
    /**
     * @param  array<string, mixed>  $args
     *
     * @throws \Stripe\Exception\ApiErrorException
     */
    public function __invoke($_, array $args): string
    {
        $tenant = tenant();

        Assert::isInstanceOf($tenant, Tenant::class);

        if (Setup::waitConnectStripe()->isNot($tenant->subscription_setup)) {
            throw new BadRequestHttpException();
        }

        UserActivity::log(
            name: 'member.stripe.init',
        );

        Segment::track([
            'userId' => (string) auth()->id(),
            'event' => 'tenant_member_stripe_initialized',
            'properties' => [
                'tenant_uid' => $tenant->id,
                'tenant_name' => $tenant->name,
            ],
            'context' => [
                'groupId' => $tenant->id,
            ],
        ]);

        $stripe = Cashier::stripe();

        $url = $this->returnRrl();

        $link = $stripe->accountLinks->create(
            [
                'account' => $this->accountId($stripe),
                'refresh_url' => $url,
                'return_url' => $url,
                'type' => 'account_onboarding',
            ],
        );

        return $link->url;
    }

    protected function accountId(StripeClient $stripe): string
    {
        /** @var Tenant $tenant */
        $tenant = tenant();

        /** @var string|null $id */
        $id = $tenant->getAttribute('stripe_account_id');

        if (!empty($id)) {
            return $id;
        }

        $account = $stripe->accounts->create(['type' => 'express']);

        $tenant->update(['stripe_account_id' => $account->id]);

        return $account->id;
    }

    protected function returnRrl(): string
    {
        $base = 'stori.press';

        $env = app()->environment();

        if ($env === 'staging') {
            $base = 'storipress.pro';
        } elseif ($env === 'development') {
            $base = 'storipress.dev';
        } elseif ($env === 'local') {
            $base = 'localhost:3333';
        }

        return sprintf('https://%s/stripe-connected.html', $base);
    }
}
