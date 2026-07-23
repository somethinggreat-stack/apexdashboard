<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Round-outcome metrics captured when a report is pulled / deletions recorded:
 * how many items were deleted, flipped positive/negative or newly added, plus
 * before/after scores per bureau. All optional — the old *_disputed columns stay
 * so existing timeline entries keep rendering.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('process_steps', function (Blueprint $table) {
            foreach ([
                'total_deletions',
                'updated_to_positive',
                'updated_to_negative',
                'items_added',
                'experian_score_before',
                'experian_score_now',
                'transunion_score_before',
                'transunion_score_now',
                'equifax_score_before',
                'equifax_score_now',
            ] as $col) {
                if (!Schema::hasColumn('process_steps', $col)) {
                    $table->unsignedSmallInteger($col)->nullable();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('process_steps', function (Blueprint $table) {
            $table->dropColumn([
                'total_deletions', 'updated_to_positive', 'updated_to_negative', 'items_added',
                'experian_score_before', 'experian_score_now',
                'transunion_score_before', 'transunion_score_now',
                'equifax_score_before', 'equifax_score_now',
            ]);
        });
    }
};
