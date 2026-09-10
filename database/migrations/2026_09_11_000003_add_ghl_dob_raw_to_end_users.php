<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The date of birth exactly as the client typed it on the GoHighLevel form.
 *
 * date_of_birth stays a real date column so ages, sorting and the rest keep
 * working. This holds the original text alongside it, and the profile shows it
 * verbatim for a client who came from GoHighLevel — no reformatting, nothing to
 * second-guess when comparing against the submission.
 *
 * Only populated by the GHL sync, so every other business owner is unaffected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('end_users', function (Blueprint $table) {
            $table->string('ghl_dob_raw', 32)->nullable()->after('ghl_synced_at');
        });
    }

    public function down(): void
    {
        Schema::table('end_users', function (Blueprint $table) {
            $table->dropColumn('ghl_dob_raw');
        });
    }
};
