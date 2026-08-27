<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Retro-fix for the "1st round auto-marked on add" bug.
 *
 * New clients used to be stamped rounds = ['1st Round'] the instant they were
 * added, so unworked files (still in New Clients / New Client Errors) showed a
 * started round, ticking days, and a false "past its 30 days" warning. Intake
 * no longer marks a round at all, and a round's start date now derives from its
 * first logged step — so this pass simply UNMARKS the leftover "1st Round" on
 * intake-stage clients that have never had a single process step logged.
 *
 * Scope is deliberately limited to the New Clients (pending_review) and New
 * Client Errors (error) buckets: those clients are pre-work, so clearing their
 * rounds is safe. Worked/active clients are untouched here — the model already
 * reads them as "not started" until their first step, and self-heals on it.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('end_users')
            ->whereIn('intake_status', ['pending_review', 'error'])
            ->whereNull('deleted_at')
            ->where('rounds', 'like', '%Round%')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('process_steps')
                    ->whereColumn('process_steps.end_user_id', 'end_users.id');
            })
            ->update([
                'rounds'      => json_encode([]),
                'round_dates' => null,
            ]);
    }

    public function down(): void
    {
        // Irreversible data cleanup — nothing to restore (the auto-mark was a bug).
    }
};
