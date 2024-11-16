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
        Schema::create('webhooks', function (Blueprint $table) {
            $table->string('id')
                ->collation('utf8mb4_bin')
                ->primary();

            $table->string('platform');

            $table->string('topic')
                ->collation('utf8mb4_bin');

            $table->text('url');

            $table->dateTime('activated_at')->nullable();

            $table->dateTime('created_at');

            $table->dateTime('updated_at');

            $table->dateTime('deleted_at')->nullable();

            $table->index(['topic', 'platform', 'deleted_at']);

            $table->index(['platform', 'topic', 'deleted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhooks');
    }
};
