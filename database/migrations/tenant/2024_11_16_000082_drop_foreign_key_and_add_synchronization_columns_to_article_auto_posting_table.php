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
        Schema::table('article_auto_postings', function (Blueprint $table) {
            $table->dropForeign(['article_id']);

            $table->dropForeign(['integration_key']);
        });

        Schema::table('article_auto_postings', function (Blueprint $table) {
            $table->renameColumn('integration_key', 'platform');
        });

        Schema::table('article_auto_postings', function (Blueprint $table) {
            $table->string('target_id')
                ->nullable()
                ->after('platform');

            $table->string('domain')
                ->nullable()
                ->after('target_id');

            $table->string('prefix')
                ->nullable()
                ->after('domain');

            $table->string('pathname')
                ->nullable()
                ->after('prefix');

            $table->index(['article_id', 'platform']);

            $table->index(['platform', 'target_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('article_auto_postings', function (Blueprint $table) {
            $table->dropIndex(['article_id', 'platform']);

            $table->dropIndex(['platform', 'target_id']);
        });

        Schema::table('article_auto_postings', function (Blueprint $table) {
            $table->dropColumn(['target_id', 'domain', 'prefix', 'pathname']);
        });

        Schema::table('article_auto_postings', function (Blueprint $table) {
            $table->renameColumn('platform', 'integration_key');
        });

        Schema::table('article_auto_postings', function (Blueprint $table) {
            $table->foreign('integration_key')
                ->references('key')
                ->on('integrations')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreign('article_id')
                ->references('id')
                ->on('articles')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
        });
    }
};
