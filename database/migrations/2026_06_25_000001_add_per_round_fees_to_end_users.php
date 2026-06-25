<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('end_users', function (Blueprint $table) {
            // Per-round overrides of the client's per-round fee, keyed by round
            // number, e.g. {"1": 12.00}. A round that is absent (or null) falls
            // back to the client's flat per_round_fee, then the BO default.
            // This lets a client be charged a different amount on a specific
            // round (e.g. $12 for Round 1) while the rest use the default.
            $table->json('per_round_fees')->nullable()->after('per_round_fee');
        });
    }

    public function down(): void
    {
        Schema::table('end_users', function (Blueprint $table) {
            $table->dropColumn('per_round_fees');
        });
    }
};
