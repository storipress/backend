<?php

namespace App\Console\Migrations;

use App\Events\Partners\Shopify\RedirectionsSyncing;
use App\Listeners\Partners\Shopify\HandleRedirections;
use App\Models\Tenant;
use App\Models\Tenants\Integration;
use App\SDK\Shopify\Shopify;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\LazyCollection;

class MigrateShopifyRedirections extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:shopify-redirections {--tenants=*}';

    /**
     * Execute the console command.
     */
    public function handle(Shopify $app): int
    {
        $specific = ! empty($this->option('tenants'));

        if ($specific) {
            $tenants = Tenant::initialized()
                ->whereIn('id', $this->option('tenants'))
                ->lazyById();
        } else {
            /** @var LazyCollection<int, Tenant> $tenants */
            $tenants = Tenant::initialized()->lazyById();
        }

        foreach ($tenants as $tenant) {
            if ($tenant->shopify_data === null) {
                if ($specific) {
                    $this->error('%s: can not find shopify_data', $tenant->id);
                }

                continue;
            }

            $valid = $tenant->run(function (Tenant $tenant) use ($specific) {
                $shopify = Integration::where('key', 'shopify')
                    ->whereNotNull('internals')
                    ->first();

                if ($shopify === null) {
                    if ($specific) {
                        $this->error('%s: can not find connected data', $tenant->id);
                    }

                    return false;
                }

                /** @var array<mixed> $internals */
                $internals = $shopify->internals;

                // ensure has write_themes scope
                $scopes = Arr::get($internals, 'scopes', []);

                if (! is_array($scopes)) {
                    if ($specific) {
                        $this->error('%s: does not have scopes field', $tenant->id);
                    }

                    return false;
                }

                // have not reauthorize
                if (! in_array('write_content', $scopes)) {
                    if ($specific) {
                        $this->error('%s: does not have required scope (write_content)', $tenant->id);
                    }

                    return false;
                }

                return true;
            });

            if (! $valid) {
                continue;
            }

            $event = new RedirectionsSyncing($tenant->id);

            $listener = new HandleRedirections($app);

            $listener->handle($event);
        }

        return self::SUCCESS;
    }
}
