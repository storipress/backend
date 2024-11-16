<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('subscriber_events', function (Blueprint $table) {
            $table->uuid('anonymous_id')
                ->nullable()
                ->index()
                ->after('id');
        });

        $exists = DB::table('subscribers')->where('id', '=', 0)->exists();

        if ($exists) {
            return;
        }

        $id = DB::table('subscribers')->insertGetId([
            'id' => 0,
            'newsletter' => false,
            'signed_up_source' => 'system',
        ]);

        DB::table('subscribers')->where('id', '=', $id)->update(['id' => 0]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriber_events', function (Blueprint $table) {
            $table->dropColumn(['anonymous_id']);
        });
    }
};
