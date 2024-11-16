<?php

namespace App\Console\Commands;

use App\Builder\ReleaseEventsBuilder;
use App\Models\Tenant;
use App\Models\Tenants\ReleaseEvent;
use Illuminate\Console\Command;

class BuildReleaseEvents extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'release:build {--force}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Build release events';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $builder = new ReleaseEventsBuilder();

        $force = $this->option('force');

        tenancy()->runForMultiple(
            null,
            function (Tenant $tenant) use ($builder, $force) {
                if (! $tenant->initialized) {
                    return;
                }

                $query = ReleaseEvent::whereNull('release_id');

                if ($force === false) {
                    $query->where('attempts', '<', 3);
                }

                $exists = $query->exists();

                if (! $exists) {
                    return;
                }

                $builder->run();
            },
        );

        return self::SUCCESS;
    }
}
