<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

final class CreateTenantBouncerTables extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('abilities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('title')->nullable();
            $table->bigInteger('entity_id')->unsigned()->nullable();
            $table->string('entity_type')->nullable();
            $table->boolean('only_owned')->default(false);
            $table->json('options')->nullable();
            $table->integer('scope')->nullable()->index();
            $table->dateTime('created_at');
            $table->dateTime('updated_at');
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('title')->nullable();
            $table->integer('level')->unsigned()->nullable();
            $table->integer('scope')->nullable()->index();
            $table->dateTime('created_at');
            $table->dateTime('updated_at');

            $table->unique(['name', 'scope']);
        });

        Schema::create('assigned_roles', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('role_id')->unsigned()->index();
            $table->bigInteger('entity_id')->unsigned();
            $table->string('entity_type');
            $table->bigInteger('restricted_to_id')->unsigned()->nullable();
            $table->string('restricted_to_type')->nullable();
            $table->integer('scope')->nullable()->index();

            $table->index(['entity_id', 'entity_type', 'scope']);

            $table->foreign('role_id')
                ->references('id')
                ->on('roles')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('ability_id')->unsigned()->index();
            $table->bigInteger('entity_id')->unsigned()->nullable();
            $table->string('entity_type')->nullable();
            $table->boolean('forbidden')->default(false);
            $table->integer('scope')->nullable()->index();

            $table->index(['entity_id', 'entity_type', 'scope']);

            $table->foreign('ability_id')
                ->references('id')
                ->on('abilities')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('assigned_roles');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('abilities');
    }
}
