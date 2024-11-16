<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

final class CreateTenantInvitationAccessesTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('invitation_accesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invitation_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->ipAddress('ip');
            $table->text('user_agent')->nullable();
            $table->dateTime('accessed_at')->useCurrent();

            $table->index(['invitation_id', 'accessed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invitation_accesses');
    }
}
