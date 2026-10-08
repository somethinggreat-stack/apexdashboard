<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "Monthly Package" payment model (compensation_model = 'package'):
 * a fixed fee covers the first N rounds processed in a monthly period, and
 * every round past N is billed at an overage rate. Don Cadet pays $500 for
 * 70 rounds a month and $7 for each round after that.
 *
 * Payments for a period are recorded as time_payouts rows (the same table the
 * hourly model uses); rounds_in_period keeps the round count the payment was for.
 *
 * Switched on for Don Cadet only. Matched on the full name rather than a
 * prefix so it cannot catch a future owner whose name begins the same way.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->decimal('package_fee', 8, 2)->nullable()->after('hourly_rate');
            $table->unsignedSmallInteger('package_rounds')->nullable()->after('package_fee');
            $table->decimal('package_overage_fee', 8, 2)->nullable()->after('package_rounds');
        });

        Schema::table('time_payouts', function (Blueprint $table) {
            $table->unsignedInteger('rounds_in_period')->nullable()->after('hours_in_period');
        });

        // Exact name, ignoring case and stray spaces; never a prefix match.
        $don = DB::table('clients')->whereRaw("LOWER(TRIM(business_name)) = 'don cadet'")->first();

        if ($don) {
            DB::table('clients')->where('id', $don->id)->update([
                'compensation_model'  => 'package',
                'package_fee'         => 500,
                'package_rounds'      => 70,
                'package_overage_fee' => 7,
                'pay_cycle'           => 'monthly',
                // Keep the cycle start he already has; otherwise months run from the 1st.
                'pay_cycle_anchor'    => $don->pay_cycle_anchor ?: now()->startOfMonth()->toDateString(),
                'per_round_fee'       => null,
                'hourly_rate'         => null,
                'weekly_hours_target' => null,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('clients')->where('compensation_model', 'package')->update(['compensation_model' => 'hourly']);

        Schema::table('time_payouts', function (Blueprint $table) {
            $table->dropColumn('rounds_in_period');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['package_fee', 'package_rounds', 'package_overage_fee']);
        });
    }
};
