<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('end_users', function (Blueprint $table) {
            // Optional per-client override of the BO's flat per-round fee.
            // Null = fall back to the business owner's default per_round_fee.
            $table->decimal('per_round_fee', 10, 2)->nullable()->after('rounds');
        });
    }

    public function down(): void
    {
        Schema::table('end_users', function (Blueprint $table) {
            $table->dropColumn('per_round_fee');
        });
    }
};
