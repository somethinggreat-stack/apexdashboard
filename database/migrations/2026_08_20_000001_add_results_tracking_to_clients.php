<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Opt-in per business owner for the negative-items results system (item entry
 * at intake, EOD + monthly results reports, round approval). Enabled ONLY for
 * Clinecea Phillips for now; every other owner is unaffected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->boolean('results_tracking')->default(false)->after('custom_lists_enabled');
        });

        DB::table('clients')
            ->where('business_name', 'like', 'Clinecea%')
            ->update(['results_tracking' => true]);
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('results_tracking');
        });
    }
};
