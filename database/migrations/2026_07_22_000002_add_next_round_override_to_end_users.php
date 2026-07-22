<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Next Round Date is normally computed (current round start + 1 month). This
 * optional override lets it be set by hand from the Clients list. Null = fall
 * back to the automatic date. Round Started itself edits start_date / round_dates
 * directly, so it needs no column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('end_users', function (Blueprint $table) {
            if (!Schema::hasColumn('end_users', 'next_round_override')) {
                $table->date('next_round_override')->nullable()->after('round_dates');
            }
        });
    }

    public function down(): void
    {
        Schema::table('end_users', function (Blueprint $table) {
            $table->dropColumn('next_round_override');
        });
    }
};
