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
        Schema::create('media', function (Blueprint $table) {
            $table->id();

            $table->char('token', 36)
                ->collation('utf8mb4_bin')
                ->unique();

            $table->string('tenant_id', 191)
                ->collation('utf8mb4_bin')
                ->nullable();

            $table->string('model_type', 191)
                ->collation('utf8mb4_bin');

            $table->string('model_id', 191)
                ->collation('utf8mb4_bin');

            $table->string('collection', 191);

            $table->string('path');

            $table->string('mime')
                ->collation('utf8mb4_bin')
                ->index();

            $table->integer('size')
                ->unsigned();

            $table->integer('width')
                ->unsigned();

            $table->integer('height')
                ->unsigned();

            $table->string('blurhash')
                ->collation('utf8mb4_bin')
                ->nullable();

            $table->dateTime('created_at');

            $table->dateTime('updated_at');

            $table->dateTime('deleted_at')
                ->nullable();

            $table->index(['model_type', 'model_id', 'collection', 'tenant_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
