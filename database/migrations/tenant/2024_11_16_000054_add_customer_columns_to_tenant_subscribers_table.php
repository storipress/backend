<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('subscribers', function (Blueprint $table) {
            $table->string('pm_type')
                ->nullable()
                ->after('stripe_id');

            $table->string('pm_last_four', 4)
                ->nullable()
                ->after('pm_type');

            $table->dateTime('trial_ends_at')
                ->nullable()
                ->after('pm_last_four');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscribers', function (Blueprint $table) {
            $table->dropColumn([
                'pm_type',
                'pm_last_four',
                'trial_ends_at',
            ]);
        });
    }
};
