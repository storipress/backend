<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTenantSubscribersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('subscribers', function (Blueprint $table) {
            $table->id();

            $table->string('stripe_id')
                ->collation('utf8mb4_bin')
                ->nullable()
                ->index();

            $table->boolean('newsletter')
                ->default(true);

            $table->dateTime('first_paid_at')
                ->nullable();

            $table->dateTime('subscribed_at')
                ->nullable();

            $table->dateTime('renew_on')
                ->nullable();

            $table->dateTime('canceled_at')
                ->nullable();

            $table->dateTime('expire_on')
                ->nullable();

            $table->string('signed_up_source');

            $table->string('paid_up_source')
                ->nullable();

            $table->integer('revenue')
                ->unsigned()
                ->default(0);

            $table->integer('activity')
                ->unsigned()
                ->default(0);

            $table->integer('active_days_last_30')
                ->unsigned()
                ->default(0);

            $table->integer('comments_total')
                ->unsigned()
                ->default(0);

            $table->integer('comments_last_7')
                ->unsigned()
                ->default(0);

            $table->integer('comments_last_30')
                ->unsigned()
                ->default(0);

            $table->integer('shares_total')
                ->unsigned()
                ->default(0);

            $table->integer('shares_last_7')
                ->unsigned()
                ->default(0);

            $table->integer('shares_last_30')
                ->unsigned()
                ->default(0);

            $table->integer('email_receives')
                ->unsigned()
                ->default(0);

            $table->integer('email_opens_total')
                ->unsigned()
                ->default(0);

            $table->integer('email_opens_last_7')
                ->unsigned()
                ->default(0);

            $table->integer('email_opens_last_30')
                ->unsigned()
                ->default(0);

            $table->integer('unique_email_opens_total')
                ->unsigned()
                ->default(0);

            $table->integer('unique_email_opens_last_7')
                ->unsigned()
                ->default(0);

            $table->integer('unique_email_opens_last_30')
                ->unsigned()
                ->default(0);

            $table->integer('email_link_clicks_total')
                ->unsigned()
                ->default(0);

            $table->integer('email_link_clicks_last_7')
                ->unsigned()
                ->default(0);

            $table->integer('email_link_clicks_last_30')
                ->unsigned()
                ->default(0);

            $table->integer('unique_email_link_clicks_total')
                ->unsigned()
                ->default(0);

            $table->integer('unique_email_link_clicks_last_7')
                ->unsigned()
                ->default(0);

            $table->integer('unique_email_link_clicks_last_30')
                ->unsigned()
                ->default(0);

            $table->integer('article_views_total')
                ->unsigned()
                ->default(0);

            $table->integer('article_views_last_7')
                ->unsigned()
                ->default(0);

            $table->integer('article_views_last_30')
                ->unsigned()
                ->default(0);

            $table->integer('unique_article_views_total')
                ->unsigned()
                ->default(0);

            $table->integer('unique_article_views_last_7')
                ->unsigned()
                ->default(0);

            $table->integer('unique_article_views_last_30')
                ->unsigned()
                ->default(0);

            $table->index(['newsletter', 'subscribed_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('subscribers');
    }
}
