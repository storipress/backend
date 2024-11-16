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
        Schema::create('links', function (Blueprint $table) {
            $table->id();

            $table->string('tenant_id')
                ->collation('utf8mb4_bin');

            $table->string('source')
                ->collation('utf8mb4_bin');

            $table->boolean('reference');

            $table->string('target_tenant')
                ->collation('utf8mb4_bin')
                ->nullable();

            $table->string('target_type')
                ->collation('utf8mb4_bin')
                ->nullable();

            $table->string('target_id')
                ->collation('utf8mb4_bin')
                ->nullable();

            $table->text('value')
                ->nullable();

            $table->dateTime('last_checked_at')
                ->nullable();

            $table->dateTime('created_at');

            $table->dateTime('updated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('links');
    }
};
