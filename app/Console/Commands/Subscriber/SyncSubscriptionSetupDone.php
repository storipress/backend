<?php

namespace App\Console\Commands\Subscriber;

use App\Enums\Subscription\Setup;
use App\Models\Tenant;
use Illuminate\Console\Command;

class SyncSubscriptionSetupDone extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscription:setup:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manual update the subscription setup done for old data';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        /** @var Tenant $tenant */
        foreach (Tenant::lazy() as $tenant) {
            if (! $tenant->initialized) {
                continue;
            }

            if (Setup::done()->isNot($tenant->subscription_setup)) {
                continue;
            }

            $tenant->subscription_setup_done = true;

            $tenant->save();
        }

        return self::SUCCESS;
    }
}
