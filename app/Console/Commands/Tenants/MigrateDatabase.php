<?php

namespace App\Console\Commands\Tenants;

use App\Observers\TriggerSiteRebuildObserver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Sentry\State\Scope;
use Stancl\Tenancy\Commands\Migrate;
use Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedById;

use function Sentry\captureException;
use function Sentry\withScope;

class MigrateDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenants:batch-migrate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run migrations for tenants in batch';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        TriggerSiteRebuildObserver::mute();

        $tenants = DB::table('tenants')
            ->where('initialized', true)
            ->whereNull('deleted_at')
            ->pluck('id');

        foreach ($tenants as $tenant) {
            try {
                $this->call(Migrate::class, [
                    '--force' => true,
                    '--tenants' => $tenant,
                ]);
            } catch (TenantCouldNotBeIdentifiedById $e) {
                $deleted = DB::table('tenants')
                    ->whereNotNull('deleted_at')
                    ->where('id', $tenant)
                    ->exists();

                if ($deleted) {
                    continue;
                }

                withScope(function (Scope $scope) use ($e, $tenant): void {
                    $scope->setContext('debug', [
                        'tenant' => $tenant,
                    ]);

                    captureException($e);
                });
            }

            gc_collect_cycles();
        }

        TriggerSiteRebuildObserver::unmute();

        return 0;
    }
}
