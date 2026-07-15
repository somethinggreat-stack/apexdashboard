<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a "hold / pause" flag to clients. held_at is null when the client is in
 * their normal bucket (New Clients / Errors / In Progress / Clients); when set,
 * the client is parked in the Hold/Pause list and hidden from those buckets.
 * It's orthogonal to intake_status, so resuming (held_at = null) drops them
 * straight back into whichever bucket they came from — no state to restore.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('end_users', function (Blueprint $table) {
            $table->timestamp('held_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('end_users', function (Blueprint $table) {
            $table->dropColumn('held_at');
        });
    }
};
