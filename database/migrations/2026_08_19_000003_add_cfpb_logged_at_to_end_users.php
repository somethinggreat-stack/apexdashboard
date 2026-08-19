<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When a VA enters/updates a client's CFPB login (universal or per-round) and
 * who did it — powers the CFPB Logins daily report (last-24h, per owner).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('end_users', function (Blueprint $table) {
            $table->timestamp('cfpb_logged_at')->nullable()->after('cfpb_round_credentials');
            $table->unsignedBigInteger('cfpb_logged_by_admin_id')->nullable()->after('cfpb_logged_at');
            $table->index('cfpb_logged_at');
        });
    }

    public function down(): void
    {
        Schema::table('end_users', function (Blueprint $table) {
            $table->dropIndex(['cfpb_logged_at']);
            $table->dropColumn(['cfpb_logged_at', 'cfpb_logged_by_admin_id']);
        });
    }
};
