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
        Schema::create('access_token_activities', function (Blueprint $table) {
            $table->id();

            $table->bigInteger('access_token_id')
                ->unsigned()
                ->index();

            $table->string('tenant_id')
                ->collation('utf8mb4_bin')
                ->nullable();

            $table->bigInteger('user_activity_id')
                ->unsigned()
                ->nullable();

            $table->ipAddress('ip')
                ->collation('utf8mb4_bin');

            $table->text('user_agent')
                ->nullable();

            $table->dateTime('occurred_at')
                ->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('access_token_activities');
    }
};
