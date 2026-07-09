<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * ONE-TIME backfill: every billable client (not New Clients, not Errors)
     * that already has a round marked (1st … 5th) moves to "Clients Done".
     * Applies to ALL business owners. From here on, clients only move between
     * In Progress and Clients Done via the buttons — nothing is automatic.
     *
     * Note: this only touches intake_status. It never edits `rounds`, so
     * payments/unpaid-round totals are completely unaffected.
     */
    public function up(): void
    {
        DB::table('end_users')
            // billable only: skip New Clients (pending_review) and Errors
            ->where(fn ($q) => $q->whereNull('intake_status')
                ->orWhereNotIn('intake_status', ['pending_review', 'error']))
            // has a round marked: rounds is a JSON array, empty is NULL / '[]' / ''
            ->whereNotNull('rounds')
            ->where('rounds', '!=', '[]')
            ->where('rounds', '!=', '')
            ->update(['intake_status' => 'done']);
    }

    public function down(): void
    {
        DB::table('end_users')
            ->where('intake_status', 'done')
            ->update(['intake_status' => 'approved']);
    }
};
