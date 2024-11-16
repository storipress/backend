<?php

declare(strict_types=1);

namespace App\Console\Migrations;

use App\Models\Tenant;
use App\Models\Tenants\Integration;
use Illuminate\Console\Command;

class MigrateWebflowV1Config extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:webflow-v1-config';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $now = now();

        runForTenants(function (Tenant $tenant) use ($now) {
            $webflow = Integration::find('webflow');

            if (!($webflow instanceof Integration)) {
                return;
            }

            if (empty($webflow->data) && empty($webflow->internals)) {
                return;
            }

            if (($webflow->internals['v2'] ?? false) === true) {
                return;
            }

            if (isset($webflow->internals['v1'])) {
                return;
            }

            $v1 = [
                'data' => $webflow->data,
                'internals' => $webflow->internals,
                'activated_at' => $webflow->activated_at,
            ];

            $webflow->update([
                'data' => [],
                'internals' => [
                    'v1' => $v1,
                ],
                'activated_at' => null,
                'updated_at' => $now,
            ]);

            $tenant->update(['webflow_data' => null]);
        });

        return static::SUCCESS;
    }
}
