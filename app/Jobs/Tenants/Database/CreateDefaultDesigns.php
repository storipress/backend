<?php

namespace App\Jobs\Tenants\Database;

use App\Models\Tenants\Design;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Stancl\Tenancy\Contracts\TenantWithDatabase;

final class CreateDefaultDesigns implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    protected TenantWithDatabase $tenant;

    /**
     * Create a new job instance.
     */
    public function __construct(TenantWithDatabase $tenant)
    {
        $this->tenant = $tenant;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->tenant->run(function () {
            $now = now();

            $keys = ['home', 'menu', 'other'];
            $rows = [];

            foreach ($keys as $key) {
                $rows[] = [
                    'key' => $key,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            Design::insert($rows);
        });
    }
}
