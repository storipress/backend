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
        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();

            $table->string('webhook_id')
                ->collation('utf8mb4_bin');

            $table->uuid('event_uuid')
                ->collation('utf8mb4_bin');

            $table->boolean('successful')
                ->default(false);

            $table->json('request')
                ->nullable();

            $table->json('response')
                ->nullable();

            $table->json('error')
                ->nullable();

            $table->dateTime('occurred_at');

            $table->index(['event_uuid', 'occurred_at']);

            $table->index(['webhook_id', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
    }
};
