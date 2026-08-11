<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a business owner resolve a Round Error themselves by fixing the credit-
 * monitoring login. When they save, this timestamp is set — the client moves out
 * of "Round Errors" (pending) and into "Errors Resolved by Business Owner" for
 * the VA to process. Cleared when the VA resolves it back to Clients (or re-flags
 * a new error).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('end_users', function (Blueprint $table) {
            $table->timestamp('error_resolved_by_client_at')->nullable()->after('intake_review_note');
            $table->index('error_resolved_by_client_at');
        });
    }

    public function down(): void
    {
        Schema::table('end_users', function (Blueprint $table) {
            $table->dropIndex(['error_resolved_by_client_at']);
            $table->dropColumn('error_resolved_by_client_at');
        });
    }
};
