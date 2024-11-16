<?php

use App\Enums\Release\State;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTenantReleasesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('releases', function (Blueprint $table) {
            $table->id();
            $table->integer('state')->default(State::queued());
            $table->tinyInteger('progress')->default(0);
            $table->json('meta')->nullable();
            $table->text('message')->nullable();
            $table->dateTime('aborted_at')->nullable();
            $table->dateTime('canceled_at')->nullable();
            $table->dateTime('failed_at')->nullable();
            $table->dateTime('finished_at')->nullable()->index();
            $table->dateTime('created_at')->index();
            $table->dateTime('updated_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('releases');
    }
}
