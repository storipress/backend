<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RenameOccurredOnToOccurredAtToTenantSubscriberEventsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $sqlite = Schema::getConnection()->getDriverName() === 'sqlite';

        Schema::table('subscriber_events', function (Blueprint $table) use ($sqlite) {
            if (! $sqlite) {
                $table->dropForeign(['subscriber_id']);
            }

            $table->dropIndex(['subscriber_id', 'occurred_on', 'name']);
        });

        Schema::table('subscriber_events', function (Blueprint $table) {
            $table->renameColumn('occurred_on', 'occurred_at');
        });

        Schema::table('subscriber_events', function (Blueprint $table) {
            $table->foreign('subscriber_id')
                ->references('id')
                ->on('subscribers')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->index(['subscriber_id', 'occurred_at', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $sqlite = Schema::getConnection()->getDriverName() === 'sqlite';

        Schema::table('subscriber_events', function (Blueprint $table) use ($sqlite) {
            if (! $sqlite) {
                $table->dropForeign(['subscriber_id']);
            }

            $table->dropIndex(['subscriber_id', 'occurred_at', 'name']);
        });

        Schema::table('subscriber_events', function (Blueprint $table) {
            $table->renameColumn('occurred_at', 'occurred_on');
        });

        Schema::table('subscriber_events', function (Blueprint $table) {
            $table->foreign('subscriber_id')
                ->references('id')
                ->on('subscribers')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->index(['subscriber_id', 'occurred_on', 'name']);
        });
    }
}
