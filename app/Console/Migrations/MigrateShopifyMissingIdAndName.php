<?php

namespace App\Console\Migrations;

use App\Models\Tenant;
use App\Models\Tenants\Integration;
use Illuminate\Console\Command;

class MigrateShopifyMissingIdAndName extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:shopify-missing-id-and-name';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        runForTenants(function (Tenant $tenant) {
            $shopify = Integration::where('key', 'shopify')
                ->whereNotNull('internals')
                ->first();

            if (empty($shopify)) {
                return;
            }

            $configuration = $shopify->internals;

            if (isset($configuration['id'], $configuration['name'])) {
                return;
            }

            $data = $shopify->data;

            if (empty($data)) {
                return;
            }

            if (!isset($data['id'], $data['name'])) {
                $this->error(sprintf('%s: Can not found id and name', $tenant->id));

                return;
            }

            $configuration['id'] = $data['id'];

            $configuration['name'] = $data['name'];

            $shopify->internals = $configuration;

            $shopify->save();
        });

        return static::SUCCESS;
    }
}
