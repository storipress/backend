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
        Schema::create('article_correlation', function (Blueprint $table) {
            $table->bigInteger('source_id')
                ->unsigned();

            $table->bigInteger('target_id')
                ->unsigned();

            $table->integer('correlation')
                ->unsigned();

            $table->dateTime('updated_at')
                ->useCurrent()
                ->useCurrentOnUpdate();

            $table->primary(['source_id', 'target_id']);

            $table->index(['source_id', 'target_id', 'correlation']);

            $table->index(['target_id', 'source_id', 'correlation']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('article_correlation');
    }
};
