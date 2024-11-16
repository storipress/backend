<?php

namespace App\Console\Migrations;

use App\Models\Tenant;
use App\Models\Tenants\Subscriber;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MigrateSubscriberConsistency extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:subscribers-consistency';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        runForTenants(function (Tenant $tenant) {
            $ids = Subscriber::pluck('id')->toArray();

            $result = $tenant->subscribers()->sync($ids);

            if (count($result['attached']) || count($result['detached'])) {
                Log::channel('slack')->debug(
                    'sync subscribers',
                    [
                        'tenant' => $tenant->id,
                        'diff' => $result,
                    ],
                );
            }
        });

        return static::SUCCESS;
    }
}
