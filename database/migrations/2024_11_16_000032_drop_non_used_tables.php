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
        Schema::dropIfExists('session_histories');

        Schema::dropIfExists('sessions');

        Schema::dropIfExists('activities');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->id();

            $table->string('tenant_id')
                ->nullable()
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->string('action');

            $table->string('subject_type')
                ->nullable();

            $table->bigInteger('subject_id')
                ->unsigned()
                ->nullable();

            $table->text('detail')
                ->nullable();

            $table->ipAddress('ip')
                ->nullable();

            $table->text('user_agent')
                ->nullable();

            $table->dateTime('occurred_at')
                ->useCurrent();

            $table->index(['tenant_id', 'user_id', 'occurred_at']);

            $table->index(['tenant_id', 'occurred_at']);

            $table->index(['user_id', 'occurred_at']);
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->id();
            $table->char('token', 26)->unique();
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->ipAddress('ip');
            $table->text('user_agent')->nullable();
            $table->dateTime('created_at');
            $table->dateTime('updated_at');
            $table->dateTime('deleted_at')->nullable();

            $table->index(['user_id', 'deleted_at', 'created_at']);
        });

        Schema::create('session_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->ipAddress('ip');
            $table->text('user_agent')->nullable();
            $table->dateTime('created_at')->useCurrent();

            $table->index(['session_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }
};
