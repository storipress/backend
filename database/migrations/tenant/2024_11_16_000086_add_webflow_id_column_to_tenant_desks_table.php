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
        Schema::table('desks', function (Blueprint $table) {
            $table->string('webflow_id')
                ->collation('utf8mb4_bin')
                ->nullable()
                ->after('shopify_id')
                ->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('desks', function (Blueprint $table) {
            $table->dropColumn(['webflow_id']);
        });
    }
};
