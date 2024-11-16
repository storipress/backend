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
        Schema::create('custom_field_groupable', function (Blueprint $table) {
            $table->bigInteger('custom_field_group_id')
                ->unsigned();

            $table->string('custom_field_groupable_id')
                ->collation('utf8mb4_bin');

            $table->string('custom_field_groupable_type')
                ->collation('utf8mb4_bin');

            $table->primary([
                'custom_field_group_id',
                'custom_field_groupable_id',
                'custom_field_groupable_type',
            ],
                'custom_field_groupable_primary',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('custom_field_groupable');
    }
};
