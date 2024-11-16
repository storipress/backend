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
        Schema::create('user_activities', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->string('role');

            $table->string('name');

            $table->string('subject_type')
                ->nullable();

            $table->bigInteger('subject_id')
                ->unsigned()
                ->nullable();

            $table->json('data')
                ->nullable();

            $table->ipAddress('ip');

            $table->text('user_agent');

            $table->dateTime('occurred_at')
                ->useCurrent();

            $table->index(['occurred_at', 'user_id']);

            $table->index(['occurred_at', 'role']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_activities');
    }
};
