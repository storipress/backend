<?php

namespace App\Console\Migrations;

use App\Models\Tenants\Integration;
use Illuminate\Console\Command;

class MigrateMissingIntegrations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:missing-integrations';

    /**
     * @var array<int, string>
     */
    protected array $integrations = [
        'shopify',
        'webflow',
        'zapier',
        'linkedin',
        'wordpress',
        'hubspot',
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        runForTenants(function () {
            foreach ($this->integrations as $integration) {
                Integration::firstOrCreate([
                    'key' => $integration,
                ], [
                    'data' => [],
                ]);
            }
        });

        return static::SUCCESS;
    }
}
