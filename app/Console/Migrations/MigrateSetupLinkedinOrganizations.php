<?php

namespace App\Console\Migrations;

use App\Models\Tenant;
use App\Models\Tenants\Integration;
use Illuminate\Console\Command;

class MigrateSetupLinkedinOrganizations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:setup-linkedin-organizations';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $tenants = Tenant::withoutEagerLoads()
            ->initialized()
            ->lazyById();

        runForTenants(function () {
            $linkedin = Integration::where('key', 'linkedin')->sole();

            $configuration = $linkedin->internals;

            if ($configuration === null) {
                return;
            }

            $data = $linkedin->data;

            if (!isset($data['setup_organizations'])) {
                $data['setup_organizations'] = true;
            }

            if (!isset($configuration['setup_organizations'])) {
                $configuration['setup_organizations'] = true;
            }

            $linkedin->update([
                'data' => $data,
                'internals' => $configuration,
            ]);
        }, $tenants);

        return static::SUCCESS;
    }
}
