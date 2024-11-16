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
        Schema::create('rules', function (Blueprint $table) {
            $table->id();

            $table->string('type');

            $table->integer('timer');

            $table->integer('threshold');

            $table->integer('frequency');

            $table->integer('multi_check');

            $table->boolean('exclusive')->default(false);

            $table->dateTime('activated_at')->nullable();

            $table->dateTime('last_ran_at')->nullable();

            $table->dateTime('created_at');

            $table->dateTime('updated_at');

            $table->index(['activated_at', 'type', 'exclusive']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('rules');
    }
};
