<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

final class CreateTenantsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('timezone')->default('Etc/UTC');
            $table->binary('favicon')->nullable();
            $table->string('facebook')->nullable();
            $table->string('twitter')->nullable();
            $table->boolean('blog')->default(false);
            $table->boolean('initialized')->default(false);
            $table->string('workspace')->unique();
            $table->string('custom_domain')->nullable()->unique();
            $table->string('wss_secret')->unique();
            $table->string('tenancy_db_name')->nullable()->unique();
            $table->string('tenancy_db_username')->nullable();
            $table->string('tenancy_db_password')->nullable();
            $table->json('data')->nullable();
            $table->dateTime('created_at');
            $table->dateTime('updated_at');
            $table->dateTime('deleted_at')->nullable()->index();

            $table->index(['user_id', 'deleted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
}
