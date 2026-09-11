<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks whether a client has been pushed into DisputeFox.
 *
 * DisputeFox returns a redirect URL rather than a client id, so there is no
 * identifier of theirs to key on. These columns are the only record that a push
 * happened, and they are what stops the same person being sent twice.
 *
 * Only the DisputeFox push writes here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('end_users', function (Blueprint $table) {
            $table->timestamp('disputefox_pushed_at')->nullable()->after('ghl_consent');
            $table->text('disputefox_result')->nullable()->after('disputefox_pushed_at');
        });
    }

    public function down(): void
    {
        Schema::table('end_users', function (Blueprint $table) {
            $table->dropColumn(['disputefox_pushed_at', 'disputefox_result']);
        });
    }
};
