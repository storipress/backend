<?php

namespace App\Console\Migrations;

use App\Models\Tenant;
use App\Models\Tenants\User;
use App\Models\UserStatus;
use Illuminate\Console\Command;

class MigrateUserRole extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:user-role';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        runForTenants(function (Tenant $tenant) {
            $roles = User::withoutEagerLoads()
                ->pluck('role', 'id')
                ->toArray();

            foreach ($roles as $id => $role) {
                UserStatus::withoutEagerLoads()
                    ->where('tenant_id', '=', $tenant->id)
                    ->where('user_id', '=', $id)
                    ->update(['role' => $role]);
            }
        });

        return static::SUCCESS;
    }
}
