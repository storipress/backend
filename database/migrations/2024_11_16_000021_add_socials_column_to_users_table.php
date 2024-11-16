<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddSocialsColumnToUsersTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('socials')
                ->nullable()
                ->after('website');
        });

        DB::table('users')
            ->orderBy('id')
            ->select('id', 'facebook', 'twitter', 'instagram')
            ->lazy(50)
            ->each(function (stdClass $user) {
                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['socials' => json_encode([
                        'Facebook' => $user->facebook,
                        'Twitter' => $user->twitter,
                        'Instagram' => $user->instagram,
                    ])]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['socials']);
        });
    }
}
