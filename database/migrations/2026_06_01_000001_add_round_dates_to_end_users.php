<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('end_users', function (Blueprint $table) {
            // Maps a round label ("2nd Round", "3rd Round", …) to the date that
            // round was first started. Stamped automatically server-side the
            // moment a round is added; existing dates are kept.
            $table->json('round_dates')->nullable()->after('rounds');
        });
    }

    public function down(): void
    {
        Schema::table('end_users', function (Blueprint $table) {
            $table->dropColumn('round_dates');
        });
    }
};
