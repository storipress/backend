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
        Schema::create('scraper_articles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('scraper_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->text('path');

            $table->json('data')
                ->nullable();

            $table->bigInteger('article_id')
                ->unsigned()
                ->nullable();

            $table->boolean('successful')
                ->default(false);

            $table->dateTime('scraped_at')
                ->nullable();

            $table->dateTime('created_at');

            $table->dateTime('updated_at');

            $table->dateTime('deleted_at')
                ->nullable();

            $table->index(
                ['scraper_id', 'deleted_at', 'successful', 'scraped_at'],
                'scraper_articles_counting_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scraper_articles');
    }
};
