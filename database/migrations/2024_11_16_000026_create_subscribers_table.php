<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSubscribersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('subscribers', function (Blueprint $table) {
            $table->id();

            $table->string('email')
                ->unique();

            $table->string('first_name')
                ->nullable();

            $table->string('last_name')
                ->nullable();

            $table->string('stripe_id')
                ->collation('utf8mb4_bin')
                ->nullable()
                ->index();

            $table->string('card_brand')
                ->nullable();

            $table->string('card_last_four')
                ->nullable();

            $table->string('card_expiration')
                ->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('subscribers');
    }
}
