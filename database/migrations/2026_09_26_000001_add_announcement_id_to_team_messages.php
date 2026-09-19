<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ties the copies of one announcement together.
 *
 * An announcement is sent by dropping the same message into every VA's direct chat with the
 * owner — that is where they actually look, and it means unread badges, notifications and read
 * watermarks all work without inventing anything. Each copy carries the same announcement_id,
 * so the owner's page can gather them back up and say who has read it and who has not.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('team_messages', function (Blueprint $table) {
            $table->uuid('announcement_id')->nullable()->after('client_uuid')->index();
        });
    }

    public function down(): void
    {
        Schema::table('team_messages', function (Blueprint $table) {
            $table->dropIndex(['announcement_id']);
            $table->dropColumn('announcement_id');
        });
    }
};
