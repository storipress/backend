<?php

namespace App\Listeners\Entity\Domain\WorkspaceDomainChanged;

use App\Events\Entity\Domain\WorkspaceDomainChanged;
use App\Models\CloudflarePage;
use App\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Str;
use Webmozart\Assert\Assert;

class PushConfigToCloudflare implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(WorkspaceDomainChanged $event): void
    {
        $tenant = Tenant::with(['cloudflare_page'])->find($event->tenantId);

        if (!($tenant instanceof Tenant)) {
            return;
        }

        $namespace = config('services.cloudflare.customer_site_kv_namespace');

        if (!is_not_empty_string($namespace)) {
            return;
        }

        Assert::isInstanceOf($tenant->cloudflare_page, CloudflarePage::class);

        $cf = app('cloudflare');

        $key = $tenant->customer_site_storipress_url;

        $cf->setKVKey(
            $namespace,
            $key,
            $tenant->cf_pages_domain,
        );

        $remove = Str::replace(
            $tenant->workspace,
            $event->origin,
            $tenant->customer_site_storipress_url,
        );

        Assert::string($remove);

        $cf->deleteKVKey($namespace, $remove);
    }
}
