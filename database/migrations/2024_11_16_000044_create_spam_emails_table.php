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
        Schema::create('spam_emails', function (Blueprint $table) {
            $table->id();

            $table->string('email')->unique();

            $table->integer('times');

            $table->json('records')->nullable();

            $table->dateTime('expired_at');

            $table->dateTime('created_at');

            $table->dateTime('updated_at');

            $table->index('expired_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('spam_emails');
    }
};
