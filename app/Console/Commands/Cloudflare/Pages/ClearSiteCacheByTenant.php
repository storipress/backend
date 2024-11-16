<?php

namespace App\Console\Commands\Cloudflare\Pages;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Webmozart\Assert\Assert;

use function Sentry\captureException;

class ClearSiteCacheByTenant extends Command
{
    /**
     * {@inheritdoc}
     */
    protected $signature = 'cf-pages:cache:clear {tenant}';

    /**
     * {@inheritdoc}
     */
    protected $description = 'Clear pages cache for specific tenant';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $namespace = config('services.cloudflare.kv.customer_site_cache');

        Assert::stringNotEmpty($namespace);

        $prefix = sprintf('nitro:%s:', $this->argument('tenant'));

        $cloudflare = app('cloudflare');

        do {
            try {
                $list = $cloudflare->getKVKeys($namespace, $prefix);
            } catch (ConnectionException) {
                $this->pushBack();

                return static::SUCCESS;
            } catch (RequestException $e) {
                if (in_array($e->response->status(), [500, 502, 503, 504], true)) {
                    $this->pushBack();

                    return static::SUCCESS;
                }

                captureException($e);

                return static::FAILURE;
            }

            $keys = array_column($list, 'name');

            if (empty($keys)) {
                break;
            }

            $cloudflare->deleteKVKeys($namespace, $keys);

            sleep(2);
        } while (count($keys) === 1000);

        $tenant = Tenant::withoutEagerLoads()
            ->with(['cloudflare_page'])
            ->find($this->argument('tenant'));

        if (!($tenant instanceof Tenant)) {
            return static::SUCCESS;
        }

        try {
            app('http2')->get(
                sprintf(
                    'https://%s/api/_storipress/update-cache',
                    $tenant->cf_pages_domain,
                ),
            );
        } catch (ConnectionException $e) {
            if (!Str::contains($e->getMessage(), 'Operation timed out after')) {
                captureException($e);
            }
        }

        return static::SUCCESS;
    }

    public function pushBack(): void
    {
        Artisan::queue(
            ClearSiteCacheByTenant::class,
            [
                'tenant' => $this->argument('tenant'),
            ],
        )
            ->delay(30);
    }
}
