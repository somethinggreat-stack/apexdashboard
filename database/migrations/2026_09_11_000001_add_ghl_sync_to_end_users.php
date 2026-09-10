<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Clients that arrive from Benny's GoHighLevel onboarding form.
 *
 * from_ghl drives a separate admin-only list so these are reviewed apart from
 * the normal intake queue. ghl_submission_id is unique and is the thing that
 * stops a re-run of the sync from creating the same person twice — the poller
 * has no memory of its own, it decides what is new by asking this table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('end_users', function (Blueprint $table) {
            $table->boolean('from_ghl')->default(false)->after('custom_list');
            $table->string('ghl_contact_id', 64)->nullable()->after('from_ghl');
            $table->string('ghl_submission_id', 64)->nullable()->after('ghl_contact_id');

            $table->index('from_ghl');
            $table->unique('ghl_submission_id');
        });
    }

    public function down(): void
    {
        Schema::table('end_users', function (Blueprint $table) {
            $table->dropUnique(['ghl_submission_id']);
            $table->dropIndex(['from_ghl']);
            $table->dropColumn(['from_ghl', 'ghl_contact_id', 'ghl_submission_id']);
        });
    }
};
