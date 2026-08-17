<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reason captured when a VA moves a client to Hold/Pause or back to New Clients,
 * shown on those lists next to email/phone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('end_users', function (Blueprint $table) {
            $table->string('move_reason', 1000)->nullable()->after('intake_review_note');
        });
    }

    public function down(): void
    {
        Schema::table('end_users', function (Blueprint $table) {
            $table->dropColumn('move_reason');
        });
    }
};
