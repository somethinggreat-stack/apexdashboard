<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lightweight round-approval flow (SOP §2): before starting a client's next
 * round, the team asks the business owner to proceed. A client can be marked
 * 'awaiting' (with the round number) then 'approved'. Surfaced on the EOD report.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('end_users', function (Blueprint $table) {
            $table->string('round_approval_status')->nullable()->after('next_round_override'); // null|awaiting|approved
            $table->unsignedInteger('round_approval_round')->nullable()->after('round_approval_status');
            $table->timestamp('round_approval_at')->nullable()->after('round_approval_round');
        });
    }

    public function down(): void
    {
        Schema::table('end_users', function (Blueprint $table) {
            $table->dropColumn(['round_approval_status', 'round_approval_round', 'round_approval_at']);
        });
    }
};
