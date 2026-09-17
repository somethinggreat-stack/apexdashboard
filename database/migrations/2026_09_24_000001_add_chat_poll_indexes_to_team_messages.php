<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for the two queries the chat runs most often against the shared database:
 *  - the 3s "what changed" poll: WHERE conversation_id = ? AND updated_at >= ?
 *  - the hourly retention purge: WHERE created_at < ?
 * Without them both scan the whole conversation / table, which is exactly the kind of work
 * that helped exhaust the database's connection limit once before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('team_messages', function (Blueprint $table) {
            $table->index(['conversation_id', 'updated_at'], 'team_messages_conv_updated_idx');
            $table->index('created_at', 'team_messages_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('team_messages', function (Blueprint $table) {
            $table->dropIndex('team_messages_conv_updated_idx');
            $table->dropIndex('team_messages_created_idx');
        });
    }
};
