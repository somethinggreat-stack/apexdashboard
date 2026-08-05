<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Generalise the referral-commission system from the single hardcoded "Chantal"
 * to any number of referrers (Chantal, Peter, Clear Rise CO, ...).
 *
 * - clients.referrer_id           -> which referrer (a Client flagged
 *                                    is_commission_referrer) referred this BO.
 * - commission_payouts.referrer_id-> which referrer a payout belongs to.
 * Backfills existing "referred_by_chantal" BOs and Chantal's payouts, and flags
 * Peter + Clear Rise CO as referrers so they work immediately.
 *
 * Columns are plain indexed FKs-by-convention (no DB-level constraint) so the
 * migration runs identically on MySQL and on SQLite (which can't ALTER-ADD a
 * foreign key). Referential integrity is enforced in the app layer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->unsignedBigInteger('referrer_id')->nullable()->after('is_commission_referrer');
            $table->index('referrer_id');
        });

        Schema::table('commission_payouts', function (Blueprint $table) {
            $table->unsignedBigInteger('referrer_id')->nullable()->after('referrer_name');
            $table->index('referrer_id');
        });

        // --- Backfill Chantal (the original referrer) ---
        $chantalId = DB::table('clients')->whereRaw("LOWER(TRIM(business_name)) = 'chantal'")->value('id');
        if ($chantalId) {
            DB::table('clients')->where('id', $chantalId)->update(['is_commission_referrer' => true]);
            DB::table('clients')->where('referred_by_chantal', true)->whereNull('referrer_id')
                ->update(['referrer_id' => $chantalId]);
            DB::table('commission_payouts')->whereRaw("LOWER(TRIM(referrer_name)) = 'chantal'")
                ->update(['referrer_id' => $chantalId]);
        }

        // --- Flag the two new referrers so they work out of the box ---
        DB::table('clients')->whereRaw("LOWER(TRIM(business_name)) IN ('peter', 'clear rise co')")
            ->update(['is_commission_referrer' => true]);
    }

    public function down(): void
    {
        Schema::table('commission_payouts', function (Blueprint $table) {
            $table->dropIndex(['referrer_id']);
            $table->dropColumn('referrer_id');
        });
        Schema::table('clients', function (Blueprint $table) {
            $table->dropIndex(['referrer_id']);
            $table->dropColumn('referrer_id');
        });
    }
};
