<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('users')->update([
            'pm_type' => DB::raw('`card_brand`'),
            'pm_last_four' => DB::raw('`card_last_four`'),
        ]);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'card_brand',
                'card_last_four',
                'card_expiration',
                'extra_billing_information',
                'billing_address',
                'billing_address_line_2',
                'billing_city',
                'billing_state',
                'billing_postal_code',
                'billing_country',
                'vat_id',
                'receipt_emails',
            ]);
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->renameColumn('stripe_plan', 'stripe_price');
        });

        Schema::table('subscription_items', function (Blueprint $table) {
            $table->string('stripe_product')
                ->after('stripe_id');
        });

        Schema::table('subscription_items', function (Blueprint $table) {
            $table->renameColumn('stripe_plan', 'stripe_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscription_items', function (Blueprint $table) {
            $table->renameColumn('stripe_price', 'stripe_plan');
        });

        Schema::table('subscription_items', function (Blueprint $table) {
            $table->dropColumn(['stripe_product']);
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->renameColumn('stripe_price', 'stripe_plan');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('card_brand')
                ->nullable()
                ->after('stripe_id');

            $table->string('card_last_four', 4)
                ->nullable()
                ->after('card_brand');

            $table->string('card_expiration')
                ->nullable()
                ->after('card_last_four');

            $table->text('extra_billing_information')
                ->nullable()
                ->after('card_expiration');

            $table->string('billing_address')
                ->nullable()
                ->after('extra_billing_information');

            $table->string('billing_address_line_2')
                ->nullable()
                ->after('billing_address');

            $table->string('billing_city')
                ->nullable()
                ->after('billing_address_line_2');

            $table->string('billing_state')
                ->nullable()
                ->after('billing_city');

            $table->string('billing_postal_code', 25)
                ->nullable()
                ->after('billing_state');

            $table->string('billing_country', 2)
                ->nullable()
                ->after('billing_postal_code');

            $table->string('vat_id', 50)
                ->nullable()
                ->after('billing_postal_code');

            $table->text('receipt_emails')
                ->nullable()
                ->after('vat_id');
        });

        DB::table('users')->update([
            'card_brand' => DB::raw('`pm_type`'),
            'card_last_four' => DB::raw('`pm_last_four`'),
        ]);
    }
};
