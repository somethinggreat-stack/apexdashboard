<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Round selections — the signal that drives the Daily Task and Tasks View.
 *
 * A row is written the moment a round is ADDED to a client's rounds array
 * (a VA/admin selecting the next round in the round strip, a first step
 * marking a round, or a closeout advancing to the next round) — never when a
 * later process step is logged. That is why filling missing steps (the
 * "Mark All Incomplete Complete" button) no longer credits anyone as a
 * worker: it touches process_steps, not rounds.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('round_selections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('end_user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('round');                 // 1..15
            $table->unsignedBigInteger('admin_id')->nullable();   // who selected it (null = system)
            $table->timestamp('created_at')->nullable();

            $table->index('created_at');
            $table->index(['end_user_id', 'round']);
        });

        // Backfill history — one selection per already-started round, dated to its
        // real start (round_dates override or earliest logged step), with no actor
        // since this tracking didn't exist when the rounds were selected. Noon UTC
        // keeps each row on the intended day in both the PKT shift and ET views.
        // New DBs (fresh migrate, tests) have no clients yet, so this is a no-op.
        \App\Models\EndUser::with('processSteps')->chunkById(200, function ($clients) {
            $rows = [];
            foreach ($clients as $eu) {
                foreach (($eu->rounds ?? []) as $label) {
                    $n = array_search($label, \App\Models\EndUser::ROUND_OPTIONS, true);
                    if ($n === false) {
                        continue;
                    }
                    $start = $eu->roundStartDate($n + 1);
                    if (! $start) {
                        continue;
                    }
                    $rows[] = [
                        'end_user_id' => $eu->id,
                        'round'       => $n + 1,
                        'admin_id'    => null,
                        'created_at'  => $start . ' 12:00:00',
                    ];
                }
            }
            if ($rows) {
                \App\Models\RoundSelection::insert($rows);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('round_selections');
    }
};
