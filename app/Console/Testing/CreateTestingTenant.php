<?php

namespace App\Console\Testing;

use App\Models\Subscriber;
use App\Models\Tenant;
use App\Models\Tenants\Subscriber as TenantSubscriber;
use App\Models\Tenants\User as TenantUser;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateTestingTenant extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenancy:create-testing-tenant';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a testing tenant';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (Tenant::withTrashed()->where('id', '=', 'Testing')->exists()) {
            $this->warn('Testing tenant already existed.');

            return self::FAILURE;
        }

        $attributes = [
            'email' => 'testing@storipress.com',
            'first_name' => 'Testing',
            'last_name' => 'Account',
            'verified_at' => now(),
        ];

        $user = User::updateOrCreate([
            'id' => 2,
        ], array_merge($attributes, [
            'password' => Hash::make('testing'),
        ]));

        $subscriber = Subscriber::updateOrCreate([
            'id' => 2,
        ], array_merge($attributes, [
            //
        ]));

        $tenant = Tenant::create([
            'id' => 'Testing',
            'user_id' => $user->getKey(),
            'name' => 'Testing',
            'workspace' => 'testing',
            'plan' => 'publisher',
        ]);

        $user->tenants()->attach('Testing');

        $subscriber->tenants()->attach('Testing');

        $tenant->run(function () use ($user, $subscriber) {
            TenantUser::firstOrCreate([
                'id' => $user->id,
            ], [
                //
            ]);

            TenantSubscriber::firstOrCreate([
                'id' => $subscriber->id,
            ], [
                'signed_up_source' => 'testing',
            ]);
        });

        $this->info('Testing tenant create successfully.');

        return self::SUCCESS;
    }
}
