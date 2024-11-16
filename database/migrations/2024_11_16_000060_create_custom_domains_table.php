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
        Schema::create('custom_domains', function (Blueprint $table) {
            $table->id();

            $table->string('tenant_id')
                ->collation('utf8mb4_bin')
                ->index();

            $table->string('domain')
                ->collation('utf8mb4_bin')
                ->index();

            $table->string('group')
                ->collation('utf8mb4_bin');

            $table->string('hostname')
                ->collation('utf8mb4_bin');

            $table->string('type')
                ->collation('utf8mb4_bin');

            $table->text('value');

            $table->boolean('ok')
                ->default(false);

            $table->dateTime('last_checked_at')
                ->nullable();

            $table->dateTime('created_at');

            $table->dateTime('updated_at');

            $table->index(['last_checked_at', 'ok']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('custom_domains');
    }
};
