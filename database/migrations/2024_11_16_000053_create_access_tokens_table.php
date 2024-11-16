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
        Schema::create('access_tokens', function (Blueprint $table) {
            $table->id();

            $table->string('tokenable_type')
                ->collation('utf8mb4_bin')
                ->nullable();

            $table->string('tokenable_id')
                ->collation('utf8mb4_bin')
                ->nullable();

            $table->string('name');

            $table->string('token')
                ->collation('utf8mb4_bin')
                ->unique();

            $table->json('abilities')
                ->nullable();

            $table->ipAddress('ip')
                ->collation('utf8mb4_bin');

            $table->text('user_agent')
                ->nullable();

            $table->json('data')
                ->nullable();

            $table->dateTime('last_used_at')
                ->nullable();

            $table->dateTime('expires_at');

            $table->dateTime('created_at');

            $table->dateTime('updated_at');

            $table->index(['tokenable_type', 'tokenable_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('access_tokens');
    }
};
