<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A business owner who is ALSO the referral partner (Chantal) can see her own
 * referral commission inside her portal. is_commission_referrer gates that view.
 * Pre-set for the owner literally named Chantal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (!Schema::hasColumn('clients', 'is_commission_referrer')) {
                $table->boolean('is_commission_referrer')->default(false)->after('referred_by_chantal');
            }
        });

        // Strictly the one owner named Chantal — nobody else.
        DB::table('clients')->whereRaw('LOWER(TRIM(business_name)) = ?', ['chantal'])
            ->update(['is_commission_referrer' => true]);
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('is_commission_referrer');
        });
    }
};
