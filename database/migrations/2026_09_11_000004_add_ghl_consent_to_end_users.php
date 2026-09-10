<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the client agreed to on the GoHighLevel onboarding form, kept verbatim
 * with the IP and timestamp of the submission.
 *
 * This is the evidence that they consented to be contacted by SMS and accepted
 * the terms. It lived nowhere before — the tick boxes were read and discarded —
 * which is the wrong answer the first time a client disputes a message.
 *
 * It is held here rather than in `notes` because that table requires an author,
 * and altering a shared foreign key to let a background job write there would
 * put every business owner's notes at risk for one owner's feature.
 *
 * Only the GHL sync writes this column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('end_users', function (Blueprint $table) {
            $table->text('ghl_consent')->nullable()->after('ghl_dob_raw');
        });
    }

    public function down(): void
    {
        Schema::table('end_users', function (Blueprint $table) {
            $table->dropColumn('ghl_consent');
        });
    }
};
