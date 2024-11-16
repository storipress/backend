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
        Schema::create('assistants', function (Blueprint $table) {
            $table->id();

            $table->string('uuid')
                ->collation('utf8mb4_bin')
                ->index();

            $table->string('chat_id')
                ->collation('utf8mb4_bin')
                ->index();

            $table->string('tenant_id')
                ->collation('utf8mb4_bin');

            $table->bigInteger('user_id')
                ->unsigned();

            $table->string('model')
                ->collation('utf8mb4_bin');

            $table->string('type')
                ->collation('utf8mb4_bin');

            $table->json('data');

            $table->dateTime('occurred_at')
                ->useCurrent();

            $table->index(['tenant_id', 'user_id', 'occurred_at']);
            $table->index(['user_id', 'occurred_at']);
            $table->index(['type', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assistants');
    }
};
