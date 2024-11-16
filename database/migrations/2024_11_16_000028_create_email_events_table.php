<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEmailEventsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('email_events', function (Blueprint $table) {
            $table->id();

            $table->uuid('message_id');

            $table->string('record_type');

            $table->string('recipient');

            $table->string('from')
                ->nullable();

            $table->text('description')
                ->nullable();

            $table->text('details')
                ->nullable();

            $table->string('tag')
                ->nullable();

            $table->text('metadata')
                ->nullable();

            $table->bigInteger('bounce_id')
                ->unsigned()
                ->nullable();

            $table->integer('bounce_code')
                ->unsigned()
                ->nullable();

            $table->text('bounce_content')
                ->nullable();

            $table->ipAddress('ip')
                ->nullable();

            $table->text('user_agent')
                ->nullable();

            $table->boolean('first_open')
                ->default(false);

            $table->text('link')
                ->nullable();

            $table->string('click_location')
                ->nullable();

            $table->dateTime('occurred_at')
                ->index();

            $table->text('raw');

            $table->index(['message_id', 'record_type']);
            $table->index(['recipient', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('email_events');
    }
}
