<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('analyses', function (Blueprint $table) {
            $table->integer('active_subscribers')
                ->unsigned()
                ->default(0)
                ->after('paid_subscribers');

            $table->integer('email_sends')
                ->unsigned()
                ->default(0)
                ->after('revenue');

            $table->integer('email_clicks')
                ->unsigned()
                ->default(0)
                ->after('email_opens');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('analyses', function (Blueprint $table) {
            $table->dropColumn([
                'active_subscribers',
                'email_sends',
                'email_clicks',
            ]);
        });
    }
};
