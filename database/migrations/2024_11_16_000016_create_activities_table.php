<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

final class CreateActivitiesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->id();

            $table->string('tenant_id')
                ->nullable()
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->string('action');

            $table->string('subject_type')
                ->nullable();

            $table->bigInteger('subject_id')
                ->unsigned()
                ->nullable();

            $table->text('detail')
                ->nullable();

            $table->ipAddress('ip')
                ->nullable();

            $table->text('user_agent')
                ->nullable();

            $table->dateTime('occurred_at')
                ->useCurrent();

            $table->index(['tenant_id', 'user_id', 'occurred_at']);

            $table->index(['tenant_id', 'occurred_at']);

            $table->index(['user_id', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('activities');
    }
}
