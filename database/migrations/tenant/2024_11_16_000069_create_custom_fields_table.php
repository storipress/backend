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
        Schema::create('custom_fields', function (Blueprint $table) {
            $table->id();

            $table->bigInteger('custom_field_group_id')
                ->unsigned();

            $table->string('key')
                ->collation('utf8mb4_bin')
                ->unique();

            $table->string('type')
                ->collation('utf8mb4_bin');

            $table->string('name');

            $table->text('description')
                ->nullable();

            $table->json('options');

            $table->dateTime('created_at');

            $table->dateTime('updated_at');

            $table->dateTime('deleted_at')
                ->nullable();

            $table->index(['custom_field_group_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('custom_fields');
    }
};
