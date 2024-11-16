<?php

use App\Enums\AutoPosting\State;
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
        Schema::table('article_auto_postings', function (Blueprint $table) {
            $table->integer('state')
                ->default(State::none)
                ->after('integration_key');

            $table->dateTime('scheduled_at')
                ->nullable()
                ->after('data');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('article_auto_postings', function (Blueprint $table) {
            $table->dropColumn([
                'state',
                'scheduled_at',
            ]);
        });
    }
};
