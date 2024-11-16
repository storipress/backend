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
        Schema::create('scraper_selectors', function (Blueprint $table) {
            $table->id();

            $table->foreignId('scraper_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->string('type');

            $table->text('value')
                ->nullable();

            $table->json('data')
                ->nullable();

            $table->dateTime('created_at');

            $table->dateTime('updated_at');

            $table->dateTime('deleted_at')
                ->nullable();

            $table->index(['scraper_id', 'deleted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scraper_selectors');
    }
};
