<?php

namespace App\GraphQL\Queries;

use App\Models\Tenant;
use Webmozart\Assert\Assert;

class SiteSubscriptionInfo
{
    /**
     * @param  array{}  $args
     * @return array<string, bool|string|array<mixed>|null>
     */
    public function __invoke($_, array $args): array
    {
        $tenant = tenant();

        Assert::isInstanceOf($tenant, Tenant::class);

        return [
            'name' => $tenant->name,
            'description' => $tenant->description,
            'logo' => $tenant->logo,
            'paywall_config' => $tenant->paywall_config,
            'email' => $tenant->email,
            'subscription' => $tenant->subscription,
            'newsletter' => $tenant->newsletter,
            'monthly_price' => $tenant->monthly_price,
            'monthly_price_id' => $tenant->stripe_monthly_price_id,
            'yearly_price' => $tenant->yearly_price,
            'yearly_price_id' => $tenant->stripe_yearly_price_id,
        ];
    }
}
