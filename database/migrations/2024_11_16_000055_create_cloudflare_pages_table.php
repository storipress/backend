<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cloudflare_pages', function (Blueprint $table) {
            $table->id();

            $table->string('name')
                ->collation('utf8mb4_bin');

            $table->integer('occupiers')
                ->unsigned()
                ->default(0);

            $table->json('raw')
                ->nullable();

            $table->dateTime('created_at');

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
        Schema::dropIfExists('cloudflare_pages');
    }
};
