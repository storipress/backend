<?php

use App\Enums\Article\Plan;
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
        Schema::rename('analyses', 'subscriber_analyses');

        Schema::rename('notes', 'article_thread_notes');

        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn(['shared_link', 'due_at']);
        });

        Schema::table('articles', function (Blueprint $table) {
            $table->json('document')
                ->nullable()
                ->change();

            $table->longText('html')
                ->nullable()
                ->change();

            $table->longText('plaintext')
                ->nullable()
                ->change();

            $table->integer('plan')
                ->default(Plan::free())
                ->change();

            if (Schema::getConnection()->getDriverName() === 'sqlite') {
                return;
            }

            $table->string('slug')
                ->collation('utf8mb4_unicode_520_ci')
                ->change();

            $table->string('encryption_key')
                ->collation('utf8mb4_bin')
                ->nullable()
                ->change();
        });

        Schema::table('article_author', function (Blueprint $table) {
            $table->dropColumn(['id', 'chief', 'credit', 'created_at', 'updated_at']);
        });

        Schema::table('article_author', function (Blueprint $table) {
            $table->primary(['article_id', 'user_id']);
        });

        Schema::table('article_author', function (Blueprint $table) {
            $table->dropUnique('article_author_article_id_user_id_unique');
        });

        Schema::table('blocks', function (Blueprint $table) {
            if (Schema::getConnection()->getDriverName() === 'sqlite') {
                return;
            }

            $table->char('uuid', 36)
                ->collation('utf8mb4_bin')
                ->change();
        });

        Schema::table('integrations', function (Blueprint $table) {
            $table->dropColumn(['order']);
        });

        Schema::table('layouts', function (Blueprint $table) {
            if (Schema::getConnection()->getDriverName() === 'sqlite') {
                return;
            }

            $table->string('template')
                ->collation('utf8mb4_bin')
                ->change();
        });

        Schema::table('releases', function (Blueprint $table) {
            $table->dropColumn(['progress', 'message', 'aborted_at', 'canceled_at', 'failed_at', 'finished_at']);
        });

        Schema::table('release_events', function (Blueprint $table) {
            if (Schema::getConnection()->getDriverName() === 'sqlite') {
                return;
            }

            $table->string('checksum')
                ->collation('utf8mb4_bin')
                ->index()
                ->change();
        });

        Schema::table('subscribers', function (Blueprint $table) {
            $table->dateTime('updated_at')
                ->default('2022-10-24 00:00:00')
                ->useCurrentOnUpdate();
        });

        Schema::table('subscriber_events', function (Blueprint $table) {
            $table->json('data')
                ->nullable()
                ->change();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'first_name',
                'last_name',
                'email',
                'location',
                'bio',
                'website',
                'facebook',
                'twitter',
                'instagram',
            ]);
        });

        Schema::table('user_activities', function (Blueprint $table) {
            $table->string('subject_id')
                ->nullable()
                ->change();
        });

        Schema::table('user_activities', function (Blueprint $table) {
            $table->index(['subject_type', 'subject_id', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::rename('subscriber_analyses', 'analyses');

        Schema::rename('article_thread_notes', 'notes');

        Schema::table('articles', function (Blueprint $table) {
            $table->string('shared_link')
                ->nullable();

            $table->dateTime('due_at')
                ->nullable();

            $table->unique(['shared_link'], 'articles_shared_link_unique');
        });

        Schema::table('article_author', function (Blueprint $table) {
            $table->unique(['article_id', 'user_id']);
        });

        Schema::table('article_author', function (Blueprint $table) {
            $table->dropPrimary(['article_id', 'user_id']);
        });

        Schema::table('article_author', function (Blueprint $table) {
            if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                $table->id();
            } else {
                $table->bigInteger('id')
                    ->unsigned()
                    ->nullable()
                    ->unique();
            }

            $table->boolean('chief')
                ->default(false);

            $table->boolean('credit')
                ->default(true);

            $table->dateTime('created_at')
                ->default('2022-10-24 00:00:00');

            $table->dateTime('updated_at')
                ->default('2022-10-24 00:00:00');
        });

        Schema::table('integrations', function (Blueprint $table) {
            $table->integer('order')
                ->default(0);
        });

        Schema::table('releases', function (Blueprint $table) {
            $table->integer('progress')
                ->default(0);

            $table->text('message')
                ->nullable();

            $table->dateTime('aborted_at')
                ->nullable();

            $table->dateTime('canceled_at')
                ->nullable();

            $table->dateTime('failed_at')
                ->nullable();

            $table->dateTime('finished_at')
                ->nullable()
                ->index();
        });

        Schema::table('release_events', function (Blueprint $table) {
            if (Schema::getConnection()->getDriverName() === 'sqlite') {
                return;
            }

            $table->dropIndex('release_events_checksum_index');
        });

        Schema::table('subscribers', function (Blueprint $table) {
            $table->dropColumn(['updated_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name')
                ->nullable();

            $table->string('last_name')
                ->nullable();

            $table->string('email')
                ->nullable();

            $table->string('location')
                ->nullable();

            $table->text('bio')
                ->nullable();

            $table->string('website')
                ->nullable();

            $table->string('facebook')
                ->nullable();

            $table->string('twitter')
                ->nullable();

            $table->string('instagram')
                ->nullable();
        });

        Schema::table('user_activities', function (Blueprint $table) {
            $table->dropIndex(['subject_type', 'subject_id', 'occurred_at']);
        });
    }
};
