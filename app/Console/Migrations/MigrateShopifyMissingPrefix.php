<?php

namespace App\Console\Migrations;

use App\Models\Tenants\Integration;
use Illuminate\Console\Command;

class MigrateShopifyMissingPrefix extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:shopify-missing-prefix';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        runForTenants(function () {
            $shopify = Integration::where('key', 'shopify')
                ->whereNotNull('internals')
                ->first();

            if (empty($shopify)) {
                return;
            }

            $internals = $shopify->internals;

            if (empty($internals)) {
                return;
            }

            if (! empty($internals['prefix'])) {
                return;
            }

            $internals['prefix'] = '/a/blog';

            $shopify->internals = $internals;

            $shopify->save();
        });

        return static::SUCCESS;
    }
}
