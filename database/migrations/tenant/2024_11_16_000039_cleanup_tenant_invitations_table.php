<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $sqlite = Schema::getConnection()->getDriverName() === 'sqlite';

        Schema::table('invitations', function () {
            Schema::disableForeignKeyConstraints();

            DB::table('invitations')->truncate();

            Schema::enableForeignKeyConstraints();
        });

        Schema::table('invitations', function (Blueprint $table) {
            $table->dropColumn([
                'token',
                'first_name',
                'last_name',
                'expired_at',
                'accepted_at',
            ]);

            $table->dropIndex(['created_at']);
        });

        if ($sqlite) {
            Schema::table('invitations', function (Blueprint $table) {
                $table->dropColumn(['inviter_id', 'role_id']);
            });

            Schema::table('invitations', function (Blueprint $table) {
                $table->bigInteger('inviter_id')
                    ->unsigned();

                $table->bigInteger('role_id')
                    ->unsigned();
            });
        } else {
            Schema::table('invitations', function (Blueprint $table) {
                $table->dropForeign(['inviter_id']);

                $table->dropForeign(['role_id']);

                $table->dropIndex('invitations_inviter_id_foreign');

                $table->dropIndex('invitations_role_id_foreign');

                $table->dropIndex(['email', 'accepted_at', 'revoked_at']);
            });
        }

        Schema::table('invitations', function (Blueprint $table) {
            $table->renameColumn('revoked_at', 'deleted_at');
        });

        Schema::table('invitations', function (Blueprint $table) {
            $table->index(['deleted_at']);

            $table->index(['email', 'deleted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            $table->dropIndex(['deleted_at']);

            $table->dropIndex(['email', 'deleted_at']);

            $table->renameColumn('deleted_at', 'revoked_at');
        });

        Schema::table('invitations', function (Blueprint $table) {
            $table->char('token', 26)
                ->collation('utf8mb4_bin')
                ->nullable()
                ->unique();

            $table->string('first_name')
                ->nullable();

            $table->string('last_name')
                ->nullable();

            $table->dateTime('expired_at')
                ->nullable();

            $table->dateTime('accepted_at')
                ->nullable();

            $table->foreign('inviter_id')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreign('role_id')
                ->references('id')
                ->on('roles')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->index(['created_at']);

            $table->index(['email', 'accepted_at', 'revoked_at']);
        });
    }
};
