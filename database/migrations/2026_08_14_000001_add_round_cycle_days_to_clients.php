<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-business-owner round cycle length. Some owners run 20-day rounds, some
 * 30-day. Every round date (round end, days left, next round date) and the
 * process-step week pacing derives from this. Default 30 preserves the old
 * behaviour for any owner not explicitly set.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->unsignedSmallInteger('round_cycle_days')->default(30)->after('compensation_model');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('round_cycle_days');
        });
    }
};
