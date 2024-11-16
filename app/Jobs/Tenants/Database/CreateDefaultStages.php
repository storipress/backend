<?php

namespace App\Jobs\Tenants\Database;

use App\Models\Tenants\Stage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Stancl\Tenancy\Contracts\TenantWithDatabase;

final class CreateDefaultStages implements ShouldQueue
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
            $stages = [
                [
                    'name' => 'Ideas',
                    'color' => '#FFA324',
                    'icon' => 'mdiFileEditOutline',
                    'order' => 1,
                    'ready' => false,
                    'default' => true,
                ],
                [
                    'name' => 'For Review',
                    'color' => '#0369A1',
                    'icon' => 'mdiCheckCircleOutline',
                    'order' => 2,
                    'ready' => false,
                    'default' => false,
                ],
                [
                    'name' => 'Reviewed',
                    'color' => '#44A604',
                    'icon' => 'mdiSendOutline',
                    'order' => 3,
                    'ready' => true,
                    'default' => false,
                ],
            ];

            $now = now();
            $rows = [];

            foreach ($stages as $stage) {
                $rows[] = array_merge($stage, [
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            Stage::insert($rows);
        });
    }
}
