<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Referral is always "Chantal", so it's a simple checkbox on the business owner
 * (referred_by_chantal) instead of a free-text referrer. Payments can be marked
 * test/free (is_free) — those still close the round but never count toward
 * revenue or Chantal's commission. Pre-marks the owners already identified.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (!Schema::hasColumn('clients', 'referred_by_chantal')) {
                $table->boolean('referred_by_chantal')->default(false)->after('status');
            }
        });

        Schema::table('client_payments', function (Blueprint $table) {
            if (!Schema::hasColumn('client_payments', 'is_free')) {
                $table->boolean('is_free')->default(false)->after('amount');
            }
        });

        // Carry over exactly what was already marked via the old referrer field,
        // so the owners you flagged as Chantal's stay flagged. Falls back to the
        // known names on a fresh install where that column never existed.
        if (Schema::hasColumn('clients', 'referrer_name')) {
            DB::table('clients')->whereNotNull('referrer_name')->update(['referred_by_chantal' => true]);
        } else {
            DB::table('clients')->where(function ($q) {
                $q->whereIn('business_name', ['Gawd', 'Jay', 'John Credit Repair', 'Sky Walker'])
                    ->orWhere('business_name', 'like', 'Yolanda%');
            })->update(['referred_by_chantal' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('referred_by_chantal');
        });
        Schema::table('client_payments', function (Blueprint $table) {
            $table->dropColumn('is_free');
        });
    }
};
