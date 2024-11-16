<?php

namespace App\Console\Commands\Domain;

use App\Enums\CustomDomain\Group;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis as RedisFacade;
use Redis;
use RedisException;
use Sentry\State\Scope;
use Webmozart\Assert\Assert;

use function Sentry\captureException;
use function Sentry\withScope;

class PushConfigToContentDeliveryNetwork extends Command
{
    /**
     * {@inheritdoc}
     */
    protected $signature = 'domain:push-config {--tenants=*}';

    /**
     * {@inheritdoc}
     */
    protected $description = 'Push site config to content delivery network';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $redis = RedisFacade::connection('cdn')->client();

        Assert::isInstanceOf($redis, Redis::class);

        $channel = sprintf('cdn_caddy_%s', app()->environment());

        $tenants = Tenant::withoutEagerLoads()
            ->with(['custom_domains'])
            ->whereNotNull('cloudflare_page_id');

        if (! empty($this->option('tenants'))) {
            $tenants->whereIn('id', $this->option('tenants'));
        }

        /** @var Tenant $tenant */
        foreach ($tenants->lazyById(50) as $tenant) {
            $site = $tenant->custom_domains->firstWhere('group', '=', Group::site());

            if ($site === null) {
                continue;
            }

            $config = [
                'reverse_path' => $tenant->cf_pages_url,
                'custom' => [
                    'domain' => $site->hostname,
                    'redirect_domain' => $tenant->custom_domains
                        ->firstWhere('group', '=', Group::redirect())
                        ?->hostname ?: '',
                ],
                'timestamp' => now()->timestamp,
            ];

            $payload = json_encode($config);

            $message = json_encode(['event' => 'sync', 'tenant' => $tenant->id]);

            Assert::stringNotEmpty($payload);

            Assert::stringNotEmpty($message);

            $key = sprintf('cdn_meta_%s', $tenant->id);

            try {
                $redis->set($key, $payload);

                $redis->publish($channel, $message);
            } catch (RedisException $e) {
                withScope(function (Scope $scope) use ($e, $tenant): void {
                    $scope->setContext('tenant', $tenant->attributesToArray());

                    captureException($e);
                });
            }
        }

        return static::SUCCESS;
    }
}
