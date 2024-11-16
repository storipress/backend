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
        Schema::create('cloudflare_page_deployments', function (Blueprint $table) {
            $table->string('id')
                ->collation('utf8mb4_bin')
                ->primary();

            $table->bigInteger('cloudflare_page_id')
                ->unsigned();

            $table->string('tenant_id')
                ->collation('utf8mb4_bin');

            $table->json('raw');

            $table->dateTime('created_at', 6);

            $table->dateTime('updated_at', 6);

            $table->dateTime('deleted_at')
                ->nullable();

            $table->index(['cloudflare_page_id', 'deleted_at', 'created_at'], 'cf_page_id_deleted_at_created_at');

            $table->index(['tenant_id', 'deleted_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cloudflare_page_deployments');
    }
};
