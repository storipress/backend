<?php

use App\Models\Tenants\Integration;
use App\Observers\TriggerSiteRebuildObserver;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        TriggerSiteRebuildObserver::mute();

        Integration::updateOrCreate(
            ['key' => 'slack'],
            [
                'data' => [],
            ],
        );

        TriggerSiteRebuildObserver::unmute();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        TriggerSiteRebuildObserver::mute();

        Integration::updateOrCreate(['key' => 'slack'], [
            'data' => ['webhook_url' => null, 'username' => null],
        ]);

        TriggerSiteRebuildObserver::unmute();
    }
};
