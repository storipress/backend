<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSubscriberEventsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('subscriber_events', function (Blueprint $table) {
            $table->id();

            $table->bigInteger('subscriber_id')
                ->unsigned();

            $table->string('name');

            $table->text('data')
                ->nullable();

            $table->dateTime('occurred_on')
                ->useCurrent();

            $table->index(['subscriber_id', 'occurred_on', 'name']);

            $table->index(['name', 'occurred_on']);

            $table->index(['occurred_on']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('subscriber_events');
    }
}
