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
        Schema::create('abnormal_emails', function (Blueprint $table) {
            $table->id();

            $table->uuid('message_id');

            $table->string('type')
                ->collation('utf8mb4_bin');

            $table->dateTime('created_at');

            $table->dateTime('updated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('abnormal_emails');
    }
};
