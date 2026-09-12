<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5 — @mentions + notification prefs. `message_mentions` records who each
 * message @mentions (queryable for badges/notifications); `mentions_all` marks
 * an @everyone; `notify_level` is a per-participant desktop-notification pref.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('team_messages', function (Blueprint $table) {
            $table->boolean('mentions_all')->default(false)->after('forwarded');
        });

        Schema::create('message_mentions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_message_id')->constrained('team_messages')->cascadeOnDelete();
            $table->foreignId('admin_id')->constrained('admins')->cascadeOnDelete();
            $table->unique(['team_message_id', 'admin_id']);
            $table->index('admin_id');
        });

        Schema::table('conversation_participants', function (Blueprint $table) {
            $table->string('notify_level', 12)->default('all')->after('muted');   // all | mentions | none
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_mentions');
        Schema::table('team_messages', fn (Blueprint $t) => $t->dropColumn('mentions_all'));
        Schema::table('conversation_participants', fn (Blueprint $t) => $t->dropColumn('notify_level'));
    }
};
