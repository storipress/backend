<?php

use App\Enums\Credit\State;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('credits', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->integer('amount');

            $table->integer('state')
                ->default(State::draft());

            $table->string('earned_from');

            $table->string('invoice_id')
                ->collation('utf8mb4_bin')
                ->nullable();

            $table->text('data')
                ->nullable();

            $table->dateTime('used_at')
                ->nullable();

            $table->dateTime('created_at');

            $table->dateTime('updated_at');

            $table->index(['user_id', 'earned_from']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credits');
    }
};
