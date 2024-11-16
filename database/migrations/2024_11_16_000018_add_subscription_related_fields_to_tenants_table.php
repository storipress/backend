<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSubscriptionRelatedFieldsToTenantsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('newsletter')
                ->default(false);

            $table->boolean('subscription')
                ->default(false);

            $table->string('support_email')
                ->nullable();

            $table->string('accent_color')
                ->nullable();

            $table->string('currency')
                ->nullable();

            $table->string('monthly_price')
                ->nullable();

            $table->string('yearly_price')
                ->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'newsletter',
                'subscription',
                'support_email',
                'accent_color',
                'currency',
                'monthly_price',
                'yearly_price',
            ]);
        });
    }
}
