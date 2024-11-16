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
        Schema::table('articles', function (Blueprint $table) {
            $table->index(['published_at', 'deleted_at']);

            $table->index(['deleted_at', 'id']);

            $table->index(['stage_id', 'desk_id', 'deleted_at', 'published_at']);

            $table->index(['stage_id', 'deleted_at', 'published_at', 'desk_id']);

            $table->dropIndex(['stage_id', 'deleted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->index(['stage_id', 'deleted_at']);

            $table->dropIndex(['published_at', 'deleted_at']);

            $table->dropIndex(['deleted_at', 'id']);

            $table->dropIndex(['stage_id', 'desk_id', 'deleted_at', 'published_at']);

            $table->dropIndex(['stage_id', 'deleted_at', 'published_at', 'desk_id']);
        });
    }
};
