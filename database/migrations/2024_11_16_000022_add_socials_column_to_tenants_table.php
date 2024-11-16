<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddSocialsColumnToTenantsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->text('socials')
                ->nullable()
                ->after('favicon');
        });

        DB::table('tenants')
            ->orderBy('id')
            ->select('id', 'facebook', 'twitter', 'data')
            ->lazy(50)
            ->each(function (stdClass $tenant) {
                /** @var array<string, mixed> $data */
                $data = json_decode($tenant->data ?: '[]', true);

                DB::table('tenants')
                    ->where('id', $tenant->id)
                    ->update(['socials' => json_encode([
                        'Facebook' => $tenant->facebook,
                        'Twitter' => $tenant->twitter,
                        'Instagram' => $data['instagram'] ?? null,
                    ])]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['socials']);
        });
    }
}
