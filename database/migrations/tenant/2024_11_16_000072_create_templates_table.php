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
        Schema::create('templates', function (Blueprint $table) {
            $table->id();

            $table->string('key')
                ->collation('utf8mb4_bin')
                ->unique();

            $table->string('group')
                ->collation('utf8mb4_bin')
                ->index();

            $table->string('type')
                ->collation('utf8mb4_bin')
                ->index();

            $table->text('path')
                ->collation('utf8mb4_bin');

            $table->string('name')
                ->nullable();

            $table->text('description')
                ->nullable();

            $table->dateTime('created_at');

            $table->dateTime('updated_at');

            $table->dateTime('deleted_at')
                ->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('templates');
    }
};
