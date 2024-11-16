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
        Schema::create('article_analyses', function (Blueprint $table) {
            $table->id();

            $table->bigInteger('article_id')
                ->unsigned()
                ->nullable()
                ->index();

            $table->smallInteger('year')
                ->unsigned()
                ->nullable();

            $table->tinyInteger('month')
                ->unsigned()
                ->nullable();

            $table->date('date')
                ->nullable()
                ->index();

            $table->json('data');

            $table->dateTime('updated_at')
                ->useCurrent()
                ->useCurrentOnUpdate();

            $table->index(['year', 'month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('article_analyses');
    }
};
