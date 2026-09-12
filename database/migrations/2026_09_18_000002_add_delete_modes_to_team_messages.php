<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WhatsApp-style delete: "delete for everyone" records who did it (deleted_by,
 * shown in the tombstone); "delete for me" hides a message for one person only
 * via the message_hides pivot (others still see it).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('team_messages', function (Blueprint $table) {
            $table->foreignId('deleted_by')->nullable()->after('pinned_by')->constrained('admins')->nullOnDelete();
        });

        Schema::create('message_hides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_message_id')->constrained('team_messages')->cascadeOnDelete();
            $table->foreignId('admin_id')->constrained('admins')->cascadeOnDelete();
            $table->unique(['team_message_id', 'admin_id']);
            $table->index('admin_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_hides');
        Schema::table('team_messages', fn (Blueprint $t) => $t->dropConstrainedForeignId('deleted_by'));
    }
};
