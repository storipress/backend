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
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')
                ->collation('utf8mb4_bin')
                ->default('contributor')
                ->after('id');
        });

        $roles = DB::table('roles')
            ->pluck('name', 'id')
            ->toArray();

        $assigned = DB::table('assigned_roles')
            ->pluck('role_id', 'entity_id')
            ->toArray();

        /** @var stdClass $user */
        foreach (DB::table('users')->lazyById() as $user) {
            DB::table('users')
                ->where('id', $user->id)
                ->update(['role' => $roles[$assigned[$user->id]] ?? 'contributor']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role']);
        });
    }
};
