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
        Schema::table('desks', function (Blueprint $table) {
            $table->integer('draft_articles_count')
                ->unsigned()
                ->default(0)
                ->after('order');

            $table->integer('published_articles_count')
                ->unsigned()
                ->default(0)
                ->after('draft_articles_count');

            $table->integer('total_articles_count')
                ->unsigned()
                ->default(0)
                ->after('published_articles_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('desks', function (Blueprint $table) {
            $table->dropColumn([
                'draft_articles_count',
                'published_articles_count',
                'total_articles_count',
            ]);
        });
    }
};
