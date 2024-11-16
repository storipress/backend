<?php

use App\Enums\Scraper\State;
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
        Schema::create('scrapers', function (Blueprint $table) {
            $table->id();

            $table->integer('state')
                ->default(State::initialized)
                ->index();

            $table->json('data')
                ->nullable();

            $table->integer('total')
                ->unsigned()
                ->default(0);

            $table->integer('successful')
                ->unsigned()
                ->default(0);

            $table->integer('failed')
                ->unsigned()
                ->default(0);

            $table->dateTime('started_at')
                ->nullable();

            $table->dateTime('finished_at')
                ->nullable();

            $table->dateTime('cancelled_at')
                ->nullable();

            $table->dateTime('failed_at')
                ->nullable();

            $table->dateTime('created_at');

            $table->dateTime('updated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scrapers');
    }
};
