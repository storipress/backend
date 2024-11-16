<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('desks', function (Blueprint $table) {
            $table->bigInteger('shopify_id')
                ->nullable()
                ->index()
                ->after('id');
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
            $table->dropColumn(['shopify_id']);
        });
    }
};
