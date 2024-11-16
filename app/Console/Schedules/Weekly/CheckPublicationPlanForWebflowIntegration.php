<?php

namespace App\Console\Schedules\Weekly;

use App\Console\Schedules\Command;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CheckPublicationPlanForWebflowIntegration extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $tenants = Tenant::withoutEagerLoads()
            ->with(['owner', 'owner.subscriptions'])
            ->initialized()
            ->whereJsonContainsKey('data->webflow_data->id')
            ->lazyById();

        foreach ($tenants as $tenant) {
            $plan = $tenant->owner->subscription()?->stripe_price;

            if (! is_not_empty_string($plan) || Str::contains($plan, 'blogger')) {
                Log::channel('slack')->warning(
                    '[Webflow] incorrect publication plan',
                    [
                        'tenant_id' => $tenant->id,
                        'owner_id' => $tenant->owner->id,
                        'plan' => $plan,
                    ],
                );
            }
        }

        return static::SUCCESS;
    }
}
