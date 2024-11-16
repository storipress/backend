<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEmailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('emails', function (Blueprint $table) {
            $table->id();

            $table->string('tenant_id');

            $table->bigInteger('user_id')
                ->unsigned();

            $table->integer('user_type');

            $table->bigInteger('target_id')
                ->unsigned()
                ->nullable();

            $table->string('target_type')
                ->nullable();

            $table->uuid('message_id')
                ->index();

            $table->integer('template_id')
                ->unsigned();

            $table->string('from');

            $table->string('to');

            $table->text('data');

            $table->text('subject');

            $table->text('content');

            $table->dateTime('created_at');

            $table->dateTime('updated_at');

            $table->index(['tenant_id', 'target_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('emails');
    }
}
