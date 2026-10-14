<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inactive — a client who is gone or not paying and won't be worked again
 * unless the owner says so (vs Hold: paused, expected back). An inactive
 * client also keeps held_at set, so every list that already hides held
 * clients hides them too; only the Hold list, its count and the EOD's On Hold
 * section additionally skip inactive_at. Inactive never shows in the EOD.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('end_users', function (Blueprint $table) {
            $table->timestamp('inactive_at')->nullable()->after('held_at');
            $table->index(['client_id', 'inactive_at']);
        });
    }

    public function down(): void
    {
        Schema::table('end_users', function (Blueprint $table) {
            $table->dropIndex(['client_id', 'inactive_at']);
            $table->dropColumn('inactive_at');
        });
    }
};
