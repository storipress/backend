<?php

namespace App\Jobs\Tenants;

use App\Console\Schedules\Monthly\ExpandCloudflarePages;
use App\Models\CloudflarePage;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Webmozart\Assert\Assert;

final class EnableStoripressAppDomain implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    protected Tenant $tenant;

    /**
     * Create a new job instance.
     */
    public function __construct(Tenant $tenant)
    {
        $this->tenant = $tenant;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $site = CloudflarePage::where('occupiers', '<', CloudflarePage::MAX)
            ->first();

        // 相容舊版，避免轉移時出錯，等到穩定後就可以移除。
        if ($site === null) {
            return;
        }

        if ($site->is_almost_full) {
            Artisan::queue(ExpandCloudflarePages::class, ['--isolated' => ExpandCloudflarePages::SUCCESS]);
        }

        $site->increment('occupiers');

        $this->tenant->update([
            'cloudflare_page_id' => $site->getKey(),
        ]);

        $tenantId = $this->tenant->getKey();

        Assert::stringNotEmpty($tenantId);

        $cloudflare = app('cloudflare');

        $key = $this->tenant->customer_site_storipress_url;

        $namespace = config('services.cloudflare.customer_site_kv_namespace');

        Assert::stringNotEmpty($namespace);

        $cloudflare->setKVKey($namespace, $key, $this->tenant->cf_pages_domain);
    }
}
