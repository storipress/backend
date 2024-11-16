<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class SupportSubDesksForTenantDesksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('desks', function (Blueprint $table) {
            $table->foreignId('desk_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('desks', function (Blueprint $table) {
            if (Schema::getConnection()->getDriverName() === 'sqlite') {
                return;
            }

            $table->dropForeign(['desk_id']);
        });

        Schema::table('desks', function (Blueprint $table) {
            $table->dropColumn(['desk_id']);
        });
    }
}
