<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When each @mention was recorded. Editing a message to add "@Someone" gave them a badge but
 * no notification — the notification poll only ever looks at messages NEWER than the id it
 * last saw, and an edited message keeps its old id. With this timestamp the poll can also ask
 * "was I mentioned in anything since I last checked", which is what makes an edit notify.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('message_mentions', function (Blueprint $table) {
            $table->timestamp('created_at')->nullable()->index();
        });

        // The same thing for @everyone, which has no row in message_mentions: when the message
        // STARTED saying @everyone, so a typo fix afterwards doesn't ping the whole group again.
        Schema::table('team_messages', function (Blueprint $table) {
            // Indexed so the every-few-seconds "anything new?" check is a cheap range scan
            // over the handful of non-NULL rows, not a table scan.
            $table->timestamp('mentions_all_at')->nullable()->after('mentions_all')->index();
        });
    }

    public function down(): void
    {
        Schema::table('message_mentions', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropColumn('created_at');
        });
        Schema::table('team_messages', function (Blueprint $table) {
            $table->dropIndex(['mentions_all_at']);
            $table->dropColumn('mentions_all_at');
        });
    }
};
