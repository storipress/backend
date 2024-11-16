<?php

namespace App\Console\Commands\Cloudflare\Pages;

use App\Console\Schedules\Daily\SyncCloudflarePageDeployments;
use App\Models\CloudflarePage;
use App\Models\CloudflarePageDeployment;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Artisan;
use Webmozart\Assert\Assert;

use function Sentry\captureException;

class RemoveCloudflarePagesByTenant extends Command
{
    /**
     * {@inheritdoc}
     */
    protected $signature = 'cf-pages:remove {tenant}';

    /**
     * {@inheritdoc}
     */
    protected $description = 'Cleanup cloudflare data for specific tenant';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $namespace = config('services.cloudflare.kv.customer_site');

        Assert::stringNotEmpty($namespace);

        $tenant = Tenant::withTrashed()
            ->with(['cloudflare_page'])
            ->withoutEagerLoads()
            ->find($this->argument('tenant'));

        if (! ($tenant instanceof Tenant)) {
            return static::SUCCESS;
        }

        // ensure cloudflare pages deployments are up-to-date
        Artisan::call(
            SyncCloudflarePageDeployments::class,
            [
                '--isolated' => self::SUCCESS,
            ],
        );

        $cloudflare = app('cloudflare');

        // remove all cloudflare pages deployments belong to this tenant
        $deployments = CloudflarePageDeployment::withoutEagerLoads()
            ->with(['page'])
            ->where('tenant_id', '=', $tenant->id)
            ->lazyById(50);

        foreach ($deployments as $deployment) {
            try {
                Assert::isInstanceOf($deployment->page, CloudflarePage::class);

                $deleted = $cloudflare->deletePageDeployment(
                    $deployment->page->name,
                    $deployment->id,
                    true,
                );

                Assert::true($deleted);

                $deployment->delete();
            } catch (RequestException $e) {
                // The deployment ID you have specified does not exist. Update the deployment ID and try again.
                if ($e->response->json('errors.0.code') === 8000009) {
                    $deployment->delete();

                    continue;
                }

                captureException($e);
            }
        }

        // remove mapping from kv
        $cloudflare->deleteKVKey($namespace, $tenant->site_storipress_domain);

        // remove tenant from local cloudflare page
        if (! ($tenant->cloudflare_page instanceof CloudflarePage)) {
            return static::SUCCESS;
        }

        $tenant->update(['cloudflare_page_id' => null]);

        $tenant->cloudflare_page->decrement('occupiers');

        return static::SUCCESS;
    }
}
