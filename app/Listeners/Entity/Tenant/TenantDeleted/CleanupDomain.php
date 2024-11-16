<?php

namespace App\Listeners\Entity\Tenant\TenantDeleted;

use App\Events\Entity\Domain\CustomDomainRemoved;
use App\Events\Entity\Tenant\TenantDeleted;
use App\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class CleanupDomain implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(TenantDeleted $event): void
    {
        $tenant = Tenant::withTrashed()->find($event->tenantId);

        if (!($tenant instanceof Tenant)) {
            return;
        }

        if ($tenant->custom_domains()->exists()) {
            CustomDomainRemoved::dispatch($tenant->id);
        }

        $namespace = config('services.cloudflare.customer_site_kv_namespace');

        if (!is_not_empty_string($namespace)) {
            return;
        }

        app('cloudflare')->deleteKVKey(
            $namespace,
            $tenant->customer_site_storipress_url,
        );
    }
}
