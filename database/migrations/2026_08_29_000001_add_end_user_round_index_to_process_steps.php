<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Speed up per-round lookups (round start date, closeout, week completeness)
 * with a composite index on (end_user_id, round). The list pages already eager-
 * load the step log, but single-client pages and any not-preloaded path hit
 * this directly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('process_steps', function (Blueprint $table) {
            $table->index(['end_user_id', 'round'], 'process_steps_end_user_round_idx');
        });
    }

    public function down(): void
    {
        Schema::table('process_steps', function (Blueprint $table) {
            $table->dropIndex('process_steps_end_user_round_idx');
        });
    }
};
