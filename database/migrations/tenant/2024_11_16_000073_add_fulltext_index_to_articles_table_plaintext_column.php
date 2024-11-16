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
        Schema::table('articles', function (Blueprint $table) {
            if (Schema::getConnection()->getDriverName() === 'sqlite') {
                return;
            }

            $table->fullText(['title']);

            $table->fullText(['plaintext']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            if (Schema::getConnection()->getDriverName() === 'sqlite') {
                return;
            }

            $table->dropFullText(['title']);

            $table->dropFullText(['plaintext']);
        });
    }
};
