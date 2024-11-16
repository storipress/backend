<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

final class CreateTenantArticlesTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('desk_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('layout_id')
                ->nullable()
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('stage_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->string('shared_link')->nullable()->unique();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('blurb')->nullable();
            $table->integer('order');
            $table->boolean('featured')->default(false);
            // images will use one-to-many polymorphic relation
            $table->mediumText('document')->nullable();
            $table->json('cover')->nullable();
            $table->json('seo')->nullable();
            $table->dateTime('due_at')->nullable();
            $table->dateTime('published_at')->nullable();
            $table->dateTime('created_at');
            $table->dateTime('updated_at');
            $table->dateTime('deleted_at')->nullable();

            // used for fetching target stage in specific desk
            // e.g. fetching `Apple` desk `Draft` stage articles
            $table->index(['desk_id', 'stage_id', 'deleted_at', 'order']);

            // used for fetching target stage in All desk
            $table->index(['stage_id', 'deleted_at']);

            // @todo optimize index
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
}
