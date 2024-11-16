<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSubscriptionUsagesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('subscription_usages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('subscription_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->integer('year')
                ->unsigned();

            $table->integer('month')
                ->unsigned();

            $table->date('from');

            $table->date('to');

            $table->integer('usage')
                ->unsigned()
                ->default(0);

            $table->boolean('current')
                ->default(true);

            $table->dateTime('created_at');

            $table->dateTime('updated_at');

            $table->unique(['subscription_id', 'year', 'month']);

            $table->index(['subscription_id', 'current']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('subscription_usages');
    }
}
