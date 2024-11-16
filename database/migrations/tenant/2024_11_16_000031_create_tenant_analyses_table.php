<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTenantAnalysesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('analyses', function (Blueprint $table) {
            $table->id();

            $table->integer('subscribers')
                ->unsigned()
                ->default(0);

            $table->integer('paid_subscribers')
                ->unsigned()
                ->default(0);

            $table->integer('revenue')
                ->unsigned()
                ->default(0);

            $table->integer('email_opens')
                ->unsigned()
                ->default(0);

            $table->smallInteger('year')
                ->unsigned()
                ->nullable();

            $table->tinyInteger('month')
                ->unsigned()
                ->nullable();

            $table->date('date')
                ->nullable()
                ->index();

            $table->index(['year', 'month']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('analyses');
    }
}
