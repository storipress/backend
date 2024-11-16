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
        Schema::create('ai_analyses', function (Blueprint $table) {
            $table->id();

            $table->string('target_type')
                ->collation('utf8mb4_bin');

            $table->bigInteger('target_id')
                ->unsigned();

            $table->uuid('paragraph_id')
                ->nullable()
                ->collation('utf8mb4_bin');

            $table->string('type')
                ->collation('utf8mb4_bin');

            $table->json('data');

            $table->string('checksum')
                ->collation('utf8mb4_bin');

            $table->dateTime('created_at');

            $table->dateTime('updated_at');

            $table->index(['target_type', 'target_id', 'type', 'paragraph_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_analyses');
    }
};
