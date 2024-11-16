<?php

namespace App\Console\Schedules\Hourly;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;
use Webmozart\Assert\Assert;

use function Sentry\captureException;

class AssignCloudflarePagesKV extends Command
{
    /**
     * {@inheritdoc}
     */
    protected $hidden = false;

    /**
     * {@inheritdoc}
     */
    protected $signature = 'cf-pages:kv {--tenants=*}';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $cloudflare = app('cloudflare');

        $namespace = config('services.cloudflare.kv.customer_site');

        Assert::stringNotEmpty($namespace);

        $tenants = Tenant::withoutEagerLoads()
            ->with(['cloudflare_page'])
            ->whereNotNull('cloudflare_page_id');

        if (! empty($this->option('tenants'))) {
            $tenants->whereIn('id', $this->option('tenants'));
        }

        $data = [];

        foreach ($tenants->lazyById(50) as $tenant) {
            $data[] = [
                'key' => $tenant->site_storipress_domain,
                'value' => $tenant->cf_pages_domain,
            ];
        }

        foreach (array_chunk($data, 10_000) as $chunk) {
            try {
                $cloudflare->setKVKeys($namespace, $chunk);
            } catch (RequestException $e) {
                if (! in_array($e->response->status(), [500, 502, 503, 504], true)) {
                    captureException($e);
                }
            }

            sleep(3);
        }

        return static::SUCCESS;
    }
}
