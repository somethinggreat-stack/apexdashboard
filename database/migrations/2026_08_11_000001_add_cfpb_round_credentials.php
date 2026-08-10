<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-round CFPB credentials. The universal cfpb_email/cfpb_password stay as the
 * round-agnostic default (set at intake); this holds a round-number => {email,
 * password} map, encrypted at rest via the model cast. Rounds appear in the UI
 * as the client reaches them (driven by the existing `rounds` array).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('end_users', function (Blueprint $table) {
            $table->text('cfpb_round_credentials')->nullable()->after('cfpb_password');
        });
    }

    public function down(): void
    {
        Schema::table('end_users', function (Blueprint $table) {
            $table->dropColumn('cfpb_round_credentials');
        });
    }
};
