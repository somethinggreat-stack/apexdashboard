<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3 — presence + typing. `admins.last_seen_at` powers online/last-seen
 * (refreshed by TrackPresence middleware); `conversation_participants.typing_at`
 * powers the live "typing…" indicator (short-lived, poll-driven).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->timestamp('last_seen_at')->nullable()->after('remember_token');
        });

        Schema::table('conversation_participants', function (Blueprint $table) {
            $table->timestamp('typing_at')->nullable()->after('last_read_message_id');
        });
    }

    public function down(): void
    {
        Schema::table('admins', fn (Blueprint $t) => $t->dropColumn('last_seen_at'));
        Schema::table('conversation_participants', fn (Blueprint $t) => $t->dropColumn('typing_at'));
    }
};
