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
            $table->renameColumn('card_brand', 'pm_type');
        });

        Schema::table('subscribers', function (Blueprint $table) {
            $table->renameColumn('card_last_four', 'pm_last_four');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscribers', function (Blueprint $table) {
            $table->renameColumn('pm_last_four', 'card_last_four');
        });

        Schema::table('subscribers', function (Blueprint $table) {
            $table->renameColumn('pm_type', 'card_brand');
        });
    }
};
