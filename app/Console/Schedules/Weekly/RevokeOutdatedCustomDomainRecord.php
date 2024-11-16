<?php

namespace App\Console\Schedules\Weekly;

use App\Console\Schedules\Command;
use App\Enums\CustomDomain\Group;
use App\Models\CustomDomain;
use App\Models\Tenant;

class RevokeOutdatedCustomDomainRecord extends Command
{
    public function handle(): int
    {
        $deadline = now()->endOfDay()->subDays(31);

        $domains = CustomDomain::withoutEagerLoads()
            ->where('ok', '=', false)
            ->where('created_at', '<=', $deadline)
            ->lazyById(50);

        $tenantIds = [];

        foreach ($domains as $domain) {
            if (Group::mail()->is($domain->group)) {
                $tenantIds[] = $domain->tenant_id;
            }

            $domain->delete();

            // log event
        }

        if (empty($tenantIds)) {
            return static::SUCCESS;
        }

        $postmark = app('postmark.account');

        $tenantIds = array_values(array_unique($tenantIds));

        $tenants = Tenant::withTrashed()
            ->withoutEagerLoads()
            ->whereIn('id', $tenantIds)
            ->get();

        foreach ($tenants as $tenant) {
            if (! ($tenant instanceof Tenant)) {
                continue;
            }

            $id = $tenant->postmark_id ?: ($tenant->postmark['id'] ?? null);

            if (! is_int($id)) {
                continue;
            }

            $postmark->deleteDomain($id);

            $tenant->update([
                'postmark_id' => null,
                'postmark' => null,
                'mail_domain' => null,
            ]);
        }

        return static::SUCCESS;
    }
}
