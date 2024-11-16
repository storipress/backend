<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTenantSubscriberEventsTable extends Migration
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

            $table->foreignId('subscriber_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->bigInteger('target_id')
                ->unsigned()
                ->nullable();

            $table->string('target_type')
                ->nullable();

            $table->string('name');

            $table->text('data')
                ->nullable();

            $table->dateTime('occurred_on')
                ->useCurrent();

            $table->index(['subscriber_id', 'occurred_on', 'name']);
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
