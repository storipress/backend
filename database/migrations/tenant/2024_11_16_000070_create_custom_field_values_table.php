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
        Schema::create('custom_field_values', function (Blueprint $table) {
            $table->id();

            $table->bigInteger('custom_field_id')
                ->unsigned();

            $table->string('custom_field_morph_id')
                ->collation('utf8mb4_bin');

            $table->string('custom_field_morph_type')
                ->collation('utf8mb4_bin');

            $table->json('value');

            $table->dateTime('created_at');

            $table->dateTime('updated_at');

            $table->dateTime('deleted_at')
                ->nullable();

            $table->index([
                'custom_field_id',
                'custom_field_morph_id',
                'custom_field_morph_type',
            ],
                'custom_field_morph_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('custom_field_values');
    }
};
