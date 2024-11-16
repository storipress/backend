<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();

            $table->bigInteger('subscriber_id')
                ->unsigned();

            $table->string('name');

            $table->string('stripe_id')
                ->unique()
                ->collation('utf8mb4_bin');

            $table->string('stripe_status');

            $table->string('stripe_price')
                ->nullable();

            $table->integer('quantity')
                ->nullable();

            $table->dateTime('trial_ends_at')
                ->nullable();

            $table->dateTime('ends_at')
                ->nullable();

            $table->dateTime('created_at');

            $table->dateTime('updated_at');

            $table->index(['subscriber_id', 'stripe_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
