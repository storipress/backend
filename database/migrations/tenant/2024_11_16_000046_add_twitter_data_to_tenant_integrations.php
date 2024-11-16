<?php

use App\Models\Tenants\Integration;
use Illuminate\Database\Migrations\Migration;

return new class() extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Integration::firstOrCreate(
            ['key' => 'twitter'],
            ['data' => []],
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
