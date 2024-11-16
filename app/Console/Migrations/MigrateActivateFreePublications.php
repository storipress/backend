<?php

namespace App\Console\Migrations;

use App\Models\Tenant;
use Illuminate\Console\Command;

class MigrateActivateFreePublications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:activate-free-publications';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $tenants = Tenant::withoutEagerLoads()
            ->whereJsonContains('data->plan', 'free')
            ->lazyById(50);

        foreach ($tenants as $tenant) {
            if ($tenant->enabled === false) {
                continue;
            }

            $tenant->update(['enabled' => false]);
        }

        return static::SUCCESS;
    }
}
