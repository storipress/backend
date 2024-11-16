<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('article_reads');

        Schema::dropIfExists('article_insights');

        Schema::dropIfExists('article_snapshots');

        Schema::dropIfExists('readers');

        Schema::dropIfExists('activities');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->string('log_name')->nullable();
            $table->text('description');
            $table->bigInteger('subject_id')->unsigned()->nullable();
            $table->string('subject_type')->nullable();
            $table->string('event')->nullable();
            $table->bigInteger('causer_id')->unsigned()->nullable();
            $table->string('causer_type')->nullable();
            $table->json('properties')->nullable();
            $table->uuid('batch_uuid')->nullable();
            $table->dateTime('created_at');
            $table->dateTime('updated_at');

            $table->index('log_name');
            $table->index(['subject_id', 'subject_type']);
            $table->index(['causer_id', 'causer_type']);
        });

        Schema::create('readers', function (Blueprint $table) {
            $table->id();
            $table->string('token')->unique();
            $table->ipAddress('ip');
            $table->text('user_agent')->nullable();
            $table->json('headers');
            $table->dateTime('first_seen_at');
            $table->dateTime('last_seen_at');
        });

        Schema::create('article_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->mediumText('data');
            $table->integer('version')->unsigned();
            $table->dateTime('created_at')->useCurrent();

            $table->index(['article_id', 'version']);
        });

        Schema::create('article_insights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->integer('views')->unsigned();
            $table->integer('readers')->unsigned();
            $table->integer('stay_time')->unsigned();
            $table->date('date');
            $table->time('time')->index();

            $table->index(['date', 'time']);
        });

        Schema::create('article_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('reader_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->integer('stay_time')->unsigned();
            $table->json('data')->nullable();
            $table->dateTime('read_at')->useCurrent();

            $table->index(['article_id', 'read_at']);
        });
    }
};
