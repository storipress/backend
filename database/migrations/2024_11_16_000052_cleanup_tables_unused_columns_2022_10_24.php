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
        Schema::table('credits', function (Blueprint $table) {
            $table->json('data')
                ->nullable()
                ->change();
        });

        Schema::table('emails', function (Blueprint $table) {
            $table->json('data')
                ->nullable()
                ->change();

            $table->longText('content')
                ->nullable()
                ->change();
        });

        Schema::table('email_events', function (Blueprint $table) {
            $table->json('metadata')
                ->nullable()
                ->change();

            $table->longText('bounce_content')
                ->nullable()
                ->change();

            $table->longText('raw')
                ->change();
        });

        Schema::table('password_resets', function (Blueprint $table) {
            if (Schema::getConnection()->getDriverName() === 'sqlite') {
                return;
            }

            $table->string('token')
                ->collation('utf8mb4_bin')
                ->change();
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->json('socials')
                ->nullable()
                ->change();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->json('socials')
                ->nullable()
                ->change();
        });

        Schema::dropIfExists('subscription_usages');

        Schema::dropIfExists('tax_rates');

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'facebook',
                'twitter',
                'blog',
                'support_email',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'facebook',
                'twitter',
                'instagram',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('subscription_usages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('subscription_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->integer('year')
                ->unsigned();

            $table->integer('month')
                ->unsigned();

            $table->date('from');

            $table->date('to');

            $table->integer('usage')
                ->unsigned()
                ->default(0);

            $table->boolean('current')
                ->default(true);

            $table->dateTime('created_at');

            $table->dateTime('updated_at');

            $table->unique(['subscription_id', 'year', 'month']);

            $table->index(['subscription_id', 'current']);
        });

        Schema::create('tax_rates', function (Blueprint $table) {
            $table->id();

            $table->string('stripe_id')
                ->index();

            $table->double('percentage')
                ->index();

            $table->dateTime('created_at');

            $table->dateTime('updated_at');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->string('facebook')
                ->nullable();

            $table->string('twitter')
                ->nullable();

            $table->boolean('blog')
                ->default(false);

            $table->string('support_email')
                ->nullable();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('facebook')
                ->nullable();

            $table->string('twitter')
                ->nullable();

            $table->string('instagram')
                ->nullable();
        });
    }
};
