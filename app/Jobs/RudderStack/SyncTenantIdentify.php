<?php

namespace App\Jobs\RudderStack;

use App\Enums\User\Status;
use App\Models\Tenant;
use App\Models\Tenants\Integration;
use App\Models\Tenants\User as TenantUser;
use Illuminate\Support\Str;
use Segment\Segment;
use Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedById;

class SyncTenantIdentify extends RudderStack
{
    /**
     * Execute the job.
     *
     *
     * @throws TenantCouldNotBeIdentifiedById
     */
    public function handle(): mixed
    {
        if (app()->runningUnitTests()) {
            return null;
        }

        $tenant = Tenant::find($this->id);

        if ($tenant === null || !$tenant->initialized) {
            return null;
        }

        try {
            tenancy()->initialize($tenant);

            $integrations = Integration::get();

            $users = TenantUser::get();

            Segment::identify([
                'userId' => $tenant->id,
                'traits' => [
                    'environment' => app()->environment(),
                    'collection' => 'tenant',

                    // https://www.notion.so/storipress/9c003850affd4c049c89f6342a2da730?v=c78122b9b3b842098cf4037788e1b83a
                    'tenant_uid' => $tenant->id,
                    'tenant_name' => $tenant->name,
                    'tenant_trial_active' => $tenant->owner->onTrial(),
                    'tenant_trial_ends_at' => $tenant->owner->trialEndsAt()?->toIso8601String(),
                    'tenant_team_size' => $tenant->users()->where('tenant_user.status', Status::active())->count(),
                    'tenant_url' => $url = Str::of($tenant->url)->prepend('https://')->value(),
                    'tenant_created_at' => $tenant->created_at->toIso8601String(),
                    'tenant_created_by' => (string) $tenant->owner->id,
                    'tenant_integration_facebook' => $integrations->firstWhere('key', 'facebook')?->activated_at !== null,
                    'tenant_integration_twitter' => $integrations->firstWhere('key', 'twitter')?->activated_at !== null,
                    'tenant_integration_slack' => $integrations->firstWhere('key', 'slack')?->activated_at !== null,
                    'tenant_integration_shopify' => $integrations->firstWhere('key', 'shopify')?->activated_at !== null,
                    'tenant_integration_count' => $integrations->whereNotNull('activated_at')->count(),
                    'tenant_users' => $users
                        ->map(fn (TenantUser $user) => [
                            'tenant_uid' => $tenant->id,
                            'tenant_user_uid' => (string) $user->id,
                            'tenant_user_class' => $user->role,
                            'tenant_user_joined_time' => $user->created_at->toIso8601String(),
                            'tenant_user_suspended' => $user->status->isNot(Status::active()) ?: false,
                        ])
                        ->filter()
                        ->values()
                        ->toArray(),

                    // RudderStack reserved traits
                    // https://www.rudderstack.com/docs/event-spec/standard-events/identify/#identify-traits
                    'name' => $tenant->name,
                    'description' => $tenant->description,
                    'website' => $url,
                    'createdAt' => $tenant->created_at->toIso8601String(),
                ],
            ]);
        } finally {
            tenancy()->end();
        }

        return Segment::flush();
    }
}
