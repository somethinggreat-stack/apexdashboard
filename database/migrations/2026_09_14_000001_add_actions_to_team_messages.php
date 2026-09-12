<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WhatsApp-style message actions: reply (quote another message), emoji reactions,
 * delete-for-everyone (kept as a tombstone, not a hard delete), and a forwarded flag.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('team_messages', function (Blueprint $table) {
            $table->foreignId('reply_to_id')->nullable()->after('recipient_id')
                ->constrained('team_messages')->nullOnDelete();
            $table->json('reactions')->nullable()->after('body');   // { admin_id: "emoji" }
            $table->boolean('forwarded')->default(false)->after('reactions');
            $table->timestamp('deleted_at')->nullable()->after('read_at');
        });
    }

    public function down(): void
    {
        Schema::table('team_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reply_to_id');
            $table->dropColumn(['reactions', 'forwarded', 'deleted_at']);
        });
    }
};
